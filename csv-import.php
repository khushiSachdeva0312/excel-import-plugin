<?php
/*
Plugin Name:  CSV Import
Plugin URI:   https://swarnatek.com/
Description:  A csv import module
Version:      1.0
Author:       Swarnatek
Author URI:   https://www.swarnatek.com
License:      GPL2
License URI:  https://www.swarnatek.com
*/

(defined('ABSPATH') || exit);

// Include the Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Act on plugin activation
register_activation_hook(__FILE__, "activate_myplugin");

// Act on plugin de-activation
register_deactivation_hook(__FILE__, "deactivate_myplugin");


function activate_csvModule()
{
    // Execute tasks on Plugin activation
}

// De-activate Plugin
function deactivate_csvModule()
{
    // Execute tasks on Plugin de-activation
}

function csv_includes(){
    wp_register_style('csv_includes', plugins_url('/css/csv-style.css?'.date("h:i:sa"), __FILE__), false,'1.0','all');
    wp_enqueue_style('csv_includes');
}

add_action('admin_init','csv_includes');


function csv_import_menu()
{
    add_menu_page(
        'CSV Import Module',
        'CSV Import Module',
        'edit_plugins',
        'csv-import-module',
        'csv_import_module',
        'dashicons-media-spreadsheet',
        '26',
    );
}

add_action('admin_menu', 'csv_import_menu');

function csv_import_module() {
    ?>
    <div class="csv_page container">
        <div class="row">
            <div class="col-4">
                <div class="csv_file_upload">
                    <form action="" method="post" enctype="multipart/form-data">
                        <input type="hidden" value="true">
                        <div class="form-group">
                            <label>Choose CSV or Excel File:</label>
                            <input type="file" name="csvfile" id="fileToUpload" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <input type="submit" value="Get Import File" class="btn btn-primary" name="submit">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php 

    if (isset($_POST['submit']) && $_POST['submit'] == "Get Import File") {
        /* Allowed mime types for CSV and Excel */
        $fileMimes = array(
            'text/x-comma-separated-values',
            'text/comma-separated-values',
            'application/octet-stream',
            'application/vnd.ms-excel',
            'application/x-csv',
            'text/x-csv',
            'text/csv',
            'application/csv',
            'text/plain',
            'application/excel',
            'application/x-csv',
            'application/vnd.msexcel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // XLSX
            'application/vnd.ms-excel' // XLS
        );

        /* Validate whether selected file is a CSV or Excel file */
        if (!empty($_FILES['csvfile']['name']) && in_array($_FILES['csvfile']['type'], $fileMimes)) {
            $fileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($_FILES['csvfile']['tmp_name']);
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($fileType);
            $spreadsheet = $reader->load($_FILES['csvfile']['tmp_name']);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

            echo '<table class="table table-bordered">';
            echo '<thead><tr>';
            foreach ($sheetData[1] as $col) {
                echo '<th>' . htmlspecialchars($col ?? '', ENT_QUOTES, 'UTF-8') . '</th>';
            }
            echo '</tr></thead><tbody>';

            // Loop through the data and display it in the table
            for ($i = 2; $i <= count($sheetData); $i++) {
                // Trim whitespace from each column, ensuring no null values are passed
                $row = array_map(function($value) {
                    return trim($value ?? ''); // Use an empty string if $value is null
                }, $sheetData[$i]);

                // Check if the row is not empty
                if (array_filter($row)) { // This will return true if there are non-empty values
                    echo '<tr>';
                    foreach ($row as $col) {
                        echo '<td>' . htmlspecialchars($col ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                    }
                    echo '</tr>';
                }
            }
            
            echo '</tbody></table>';
        } else {
            echo '<div class="alert alert-danger">Please upload a valid CSV or Excel file.</div>';
        }
    }
}
?>