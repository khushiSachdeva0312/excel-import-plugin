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
register_activation_hook(__FILE__, 'activate_csvModule');
// Act on plugin de-activation
register_deactivation_hook(__FILE__, "deactivate_csvModule");


function activate_csvModule()
{
    // Execute tasks on Plugin activation
    init_db_importSheet();
}

// De-activate Plugin
function deactivate_csvModule()
{
    // Execute tasks on Plugin de-activation
}


function init_db_importSheet()
{
    global $wpdb;

    $excel_csv_import = $wpdb->prefix . 'excel_csv_import';

    if ($wpdb->get_var("SHOW TABLES LIKE '$excel_csv_import'") != $excel_csv_import) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $excel_csv_import (
            id int(11) NOT NULL AUTO_INCREMENT,
            table_headers longtext NOT NULL,
            table_data longtext NOT NULL,
            file_type varchar(255) NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Debugging: Log the SQL query
        error_log("Creating table with SQL: $sql");

        dbDelta($sql);
    } else {
        error_log("Table $excel_csv_import already exists.");
    }
}


function csv_includes()
{
    wp_register_style('csv_includes', plugins_url('/css/csv-style.css?' . date("h:i:sa"), __FILE__), false, '1.0', 'all');
    wp_enqueue_style('csv_includes');
}

add_action('admin_init', 'csv_includes');


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

    global $wpdb;
    $table_name = $wpdb->prefix . "excel_csv_import";

    // Check if the form is submitted
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
            // Get table headers in serialized way
            $tableheaders = serialize($sheetData[1]);

            // Remove the first row (headers)
            $shift = array_shift($sheetData);
            // Filter out empty values from each row
            $cleardata = array_map(function ($row) {
                return array_filter($row); // This will remove empty values from each row
            }, $sheetData);

            // Remove any empty rows after filtering
            $cleardata = array_filter($cleardata);

            // Delete existing records
            $wpdb->query("DELETE FROM $table_name");

            // Insert new data
            $tabledata = serialize($cleardata);
            $wpdb->insert(
                $table_name,
                array(
                    'table_headers' => $tableheaders,
                    'table_data' => $tabledata,
                    'file_type' => $fileType,
                )
            );
        } else {
            echo '<div class="alert alert-danger">Please upload a valid CSV or Excel file.</div>';
        }
    }

    // Fetch and display the data from the database with pagination
    $result = $wpdb->get_row("SELECT * FROM $table_name");

    if ($result) {
        // Unserialize the headers and data
        $tableheaders = unserialize($result->table_headers);
        $tabledata = unserialize($result->table_data);

        // Pagination settings
        $total_records = count($tabledata);
        $limit = 10;
        $total_pages = ceil($total_records / $limit);
        $current_page = isset($_GET['paged']) ? (int)$_GET['paged'] : 1;
        $start_from = ($current_page - 1) * $limit;

        // Sliced data for current page
        $paged_data = array_slice($tabledata, $start_from, $limit);

        echo '<table class="table table-bordered">';
        echo '<thead><tr>';
        foreach ($tableheaders as $col) {
            echo '<th>' . htmlspecialchars($col ?? '', ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr></thead><tbody>';

        // Loop through the paged data and display it in the table
        foreach ($paged_data as $row) {
            echo '<tr>';
            foreach ($row as $col) {
                echo '<td>' . htmlspecialchars($col ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table>';

        // Pagination controls
        echo '<nav><ul class="pagination">';
        if ($current_page > 1) {
            echo '<li class="page-item"><a class="page-link" href="?page=csv-import-module&paged=1"><< First</a></li>';
            echo '<li class="page-item"><a class="page-link" href="?page=csv-import-module&paged=' . ($current_page - 1) . '">< Previous</a></li>';
        }

        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == $current_page || ($i == 1 || $i == 2 || $i == $total_pages || $i == $total_pages - 1 || ($i >= $current_page - 1 && $i <= $current_page + 1))) {
                echo '<li class="page-item' . ($i == $current_page ? ' active' : '') . '">';
                echo '<a class="page-link" href="?page=csv-import-module&paged=' . $i . '">' . $i . '</a>';
                echo '</li>';
            } elseif ($i == 3 && $current_page > 4) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            } elseif ($i == $total_pages - 2 && $current_page < $total_pages - 3) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        if ($current_page < $total_pages) {
            echo '<li class="page-item"><a class="page-link" href="?page=csv-import-module&paged=' . ($current_page + 1) . '">Next ></a></li>';
            echo '<li class="page-item"><a class="page-link" href="?page=csv-import-module&paged=' . $total_pages . '">Last >></a></li>';
        }
        echo '</ul></nav>';
    } else {
        echo '<div class="alert alert-info">No data available. Please upload a CSV or Excel file.</div>';
    }
}
?>