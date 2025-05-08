<?php

require('./classes/audioGen.php');

function smoothData(array $data, int $window = 3): array {
    $smoothed = [];
    $count = count($data);
    for ($i = 0; $i < $count; $i++) {
        $sum = 0;
        $valid = 0;
        for ($j = max(0, $i - $window); $j <= min($count - 1, $i + $window); $j++) {
            if (is_numeric($data[$j])) {
                $sum += $data[$j];
                $valid++;
            }
        }
        $smoothed[] = $valid > 0 ? $sum / $valid : $data[$i];
    }
    return $smoothed;
}

$error = [];

if(!empty($_FILES['csv']['tmp_name'])){

    $sample_rate = $_POST['sample_rate'];
    $csv_file = $_FILES['csv']['tmp_name'];
    $csv_filename = $_FILES['csv']['name'];
    $filter_type = $_POST['filter_type'];
    $filter_frequency = intval($_POST['filter_frequency']);
    $gain = intval($_POST['gain']);
    $field = intval($_POST['field']) - 1;

    $test_mode = false;

    $audio = new audioGen($sample_rate);

    // Get the data.
    $testing = false;
    $data = [];
    if($testing){ // Generate a test sine wave.
        $test_length = 32000;
        for($i=0; $i<$test_length; $i++){
            $data[] = sin($i/2) * 100;
        }
    } else {  // Get the data from the CSV.
        if(($fh = fopen($csv_file, "r")) !== false){
            $i = 0;
            while(($line = fgetcsv($fh, 1000, ",")) !== false){
                if(isset($line[$field])){
                    if($i > 0){
                        if(is_numeric($line[$field])){
                            $point = floatval($line[$field]);
                            $data[] = $point;
                        }
                    }
                } else {
                    $error[] = 'Field not present in CSV';
                    break;
                }
                $i++;
            }
        }
    }

    if(!empty($data)){

        // // Perform normalization
        // $max = max($data);
        // $mean = array_sum($data) / count($data);
        // foreach($data as $point){
        //     $sample = intval(round(($point / 100) * 32767)); // Sets a value from 0 to 32767.
        //     $sample = intval(($point / $max) * 32767);
        //     $sample = max(-32768, min(32767, $sample));
        //     $audio->addSamples(pack($audio->bf,$sample));
        // }

        // Perform normalization
        $filtered = array_filter($data, 'is_numeric');
        $smoothed = smoothData($filtered, 2); // 3 = 7-sample window
        $mean = array_sum($smoothed) / count($smoothed);
        $centered = array_map(fn($x) => $x - $mean, $smoothed);
        $max = max(array_map('abs', $centered));
        if ($max == 0) $max = 1;

        foreach($centered as $point){
            $sample = intval(($point / $max) * 32767);
            $sample = max(-32768, min(32767, $sample));
            $audio->addSamples(pack($audio->bf, $sample));
        }

        // Build audio and other files
        $file_path_raw = '/run/shm/tmp.wav';
        $file_path_processed = '/run/shm/tmp_filtered.wav';
        file_put_contents($file_path_raw,$audio->buildWAV());
        if($filter_type == 'none'){
            $file_download_path = $file_path_raw;
        } else {
            passthru("sox $file_path_raw $file_path_processed $filter_type $filter_frequency gain $gain");
            $file_download_path = $file_path_processed;
        }

        // Download
        if(file_exists($file_download_path)){
            header('Content-Description: File Transfer');
            header('Content-Type: audio/wav');
            header('Content-Disposition: attachment; filename="' . basename($csv_filename) . '.wav"');
            header('Content-Length: ' . filesize($file_download_path));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Expires: 0');
            readfile($file_download_path);
            exit();
        } else {
            http_response_code(404);
            echo "File not found.";
            exit();
        }

    } else {
        $error[] = 'No data';
    }

}

$sample_rates = explode(',','8000,11025,16000,22050,32000,44100,48000');
$filters = explode(',','none,highpass,lowpass');

?>
<!doctype html>
<html>
<head>
    <title>Convert CSV to Audio</title>
    <link rel="stylesheet" href="style.css" />
</head>
<body>

    <h1>CSV to Audio</h2>
    <h2>Upload a CSV file</h2>

    <?php if(!empty($error)): ?>
        <?php foreach($error as $error_message): ?>
            <h4><?php echo $error_message; ?></h4>
        <?php endforeach; ?>
    <?php endif; ?>

    <form action="" method="post" enctype="multipart/form-data">

        <label for="csv">CSV File</label>
        <input type="file" name="csv" id="csv" accept="text/csv" />

        <label for="field">Data Field # in CSV to Use</label>
        <input type="number" name="field" id="field" min="1" max="20" step="1" value="2" />

        <label for="sample_rate">Sample Rate</label>
        <select name="sample_rate" id="sample_rate">
            <?php foreach($sample_rates as $rate): ?>
                <option value="<?php echo $rate; ?>"><?php echo $rate; ?></option>
            <?php endforeach; ?>
        </select>

        <label for="filter_type">Filter Type</label>
        <select name="filter_type" id="filter_type">
            <?php foreach($filters as $filter): ?>
                <option value="<?php echo $filter; ?>"><?php echo $filter; ?></option>
            <?php endforeach; ?>
        </select>

        <label for="filter_frequency">Filter Frequency</label>
        <input type="range" min="20" max="4000" value="40" step="5" name="filter_frequency" id="filter_frequency" />

        <label for="gain">Filter Gain</label>
        <input type="range" min="0" max="20" value="12" step="1" name="gain" id="gain" />

        <button type="submit">Get Audio</button>

    </form>

</body>
</html>