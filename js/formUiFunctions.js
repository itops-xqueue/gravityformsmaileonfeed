function updateTransactionTypeMap(selectedValue) {
    console.log(selectedValue);
    console.log('<?php echo admin_url("admin-ajax.php");?>');
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo admin_url("admin-ajax.php");?>', true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    console.log(selectedValue);
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            let keyOptions = JSON.parse(xhr.responseText);

            console.log(selectedValue);
            let mapRows = document.querySelectorAll('.gform-settings-generic-map__row');

            mapRows.forEach(function (row) {
                let keyField = row.querySelector('.select');
                if (keyField) {
                    keyField.innerHTML = '';


                    keyOptions.forEach(function (key) {
                        let option = document.createElement("option");
                        option.value = key;
                        option.text = key;
                        keyField.appendChild(option);
                    })
                }
            })
        }
    }

    // xhr.send('action=get_map_keys&transaction_type=' + selectedValue);
}









