function updateDisplayValue(field,value){
    document.getElementById(field).innerText = value;
}

function filterOptionsDisplay(){
    var filterType = document.getElementById("filter_type").value;
    if(filterType != 'none'){
        document.getElementById("filter_options").style.display = 'block';
    } else {
        document.getElementById("filter_options").style.display = 'none';
    }
}