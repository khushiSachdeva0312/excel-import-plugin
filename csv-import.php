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
require_once dirname(dirname(dirname(dirname(__FILE__)))) . "/wp-load.php";


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
            user_id int(11) NOT NULL,
            user_role varchar(255) NOT NULL,
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
    wp_register_script('csv_includes', plugins_url('/js/csv-script.js?' . date("h:i:sa"), __FILE__), array('jquery'), true);
    wp_enqueue_script('csv_includes');
    // Pass AJAX URL to the script
    wp_localize_script('csv_includes', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php') // Correct AJAX URL
    ));

}

add_action('admin_init', 'csv_includes');


function csv_import_menu()
{
    add_menu_page(
        'CSV Import Module',             // Page title
        'CSV Import Module',             // Menu title
        'read',                          // Capability required
        'csv-import-module',             // Menu slug
        'csv_import_module',             // Function to display the page
        'dashicons-media-spreadsheet',   // Menu icon
        26                               // Menu position
    );
}

add_action('admin_menu', 'csv_import_menu');

function csv_import_module()
{
    // Check if the user is an Administrator or Subscriber
    if (!current_user_can('administrator') && !current_user_can('subscriber')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    // Get the current user information
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $role = current_user_can('administrator') ? 'administrator' : 'subscriber';

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

            // Get User ID
            $user = wp_get_current_user();
            $userId = $user->ID;


            // Check if the user already has an entry in the database
            $existing_record = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id)
            );

            if ($existing_record) {
                // Update existing record
                $wpdb->update(
                    $table_name,
                    array(
                        'table_headers' => $tableheaders,
                        'table_data'    => serialize($cleardata),
                        'file_type'     => $fileType,
                    ),
                    array('user_id' => $user_id) // Condition to update based on user_id
                );
            } else {
                // Insert new record
                $wpdb->insert(
                    $table_name,
                    array(
                        'table_headers' => $tableheaders,
                        'table_data'    => serialize($cleardata),
                        'file_type'     => $fileType,
                        'user_id'       => $user_id,
                        'user_role'     => $role,
                    )
                );
            }
        } else {
            echo '<div class="alert alert-danger">Please upload a valid CSV or Excel file.</div>';
        }
    }

    if ($role === 'subscriber') {
        // Fetch only records uploaded by this user
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id));
    } elseif ($role === 'administrator') {
        // Fetch all records
        $results = $wpdb->get_results("SELECT * FROM $table_name");
    }

    if ($role === 'subscriber' && $result) {
        // Display table for the subscriber
        $table_headers = unserialize($result->table_headers);
        $table_data = unserialize($result->table_data);

        // Pagination settings
        $total_records = count($table_data);
        $limit = 10;
        $total_pages = ceil($total_records / $limit);
        $current_page = isset($_GET['paged']) ? (int)$_GET['paged'] : 1;
        $start_from = ($current_page - 1) * $limit;

        // Sliced data for current page
        $paged_data = array_slice($table_data, $start_from, $limit);

        echo '<table class="table table-bordered">';
        echo '<thead><tr>';
        foreach ($table_headers as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr></thead><tbody>';

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
    } elseif ($role === 'administrator' && $results) {
        if ($role === 'administrator' && $results) {
            echo '<table class="table table-bordered">';
            echo '<thead><tr><th>User ID</th><th>User Role</th><th>File Type</th><th>Controls</th></tr></thead><tbody>';
            foreach ($results as $record) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($record->user_id) . '</td>';
                echo '<td>' . htmlspecialchars($record->user_role) . '</td>';
                echo '<td>' . htmlspecialchars($record->file_type) . '</td>';
                echo '<td><button class="view-data-btn btn btn-primary" data-user-id="' . $record->user_id . '">View Data</button></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        // Add a container to display the fetched data
        echo '<div id="user-data-container" style="margin-top: 20px;"></div>';
    } else {
        echo '<div class="alert alert-info">No data available.</div>';
    }
    echo '</div>';
}


// AJAX handler for fetching data
function fetch_user_data()
{
    // Check if the request is valid
    if (!isset($_POST['user_id'])) {
        wp_send_json_error('Invalid Request');
        wp_die();
    }

    global $wpdb;
    $table_name = $wpdb->prefix . "excel_csv_import";

    // Get the user ID from AJAX request
    $user_id = intval($_POST['user_id']);

    // Fetch the record for the given user ID
    $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id));

    if ($result) {
        // Unserialize the headers and data
        $headers = unserialize($result->table_headers);
        $data = unserialize($result->table_data);

        // Pagination settings
        $total_records = count($data);
        $limit = 10;
        $total_pages = ceil($total_records / $limit);
        $current_page = isset($_GET['paged']) ? (int)$_GET['paged'] : 1;
        $start_from = ($current_page - 1) * $limit;

        // Sliced data for current page
        $paged_data = array_slice($data, $start_from, $limit);


        // Return the table as HTML
        $html = '<div class="userDataTable">';
        $html .= '<button type="button" class="btn btn-primary closeDataTable">Close Table</button>';
        $html .= '<h3>Data for User ID: ' . htmlspecialchars($user_id) . '</h3>';
        $html .= '<table class="table table-bordered">';
        $html .= '<thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($paged_data as $row) {
            $html .= '<tr>';
            foreach ($row as $column) {
                $html .= '<td>' . htmlspecialchars($column) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';


        // Pagination controls
        $html .= '<nav><ul class="pagination">';
        if ($current_page > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="?page=csv-import-module&paged=1"><< First</a></li>';
            $html .= '<li class="page-item"><a class="page-link" href="?page=csv-import-module&paged=' . ($current_page - 1) . '">< Previous</a></li>';
        }

        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == $current_page || ($i == 1 || $i == 2 || $i == $total_pages || $i == $total_pages - 1 || ($i >= $current_page - 1 && $i <= $current_page + 1))) {
                $html .= '<li class="page-item' . ($i == $current_page ? ' active' : '') . '">';
                $html .= '<a class="page-link" href="?page=csv-import-module&paged=' . $i . '">' . $i . '</a>';
                $html .= '</li>';
            } elseif ($i == 3 && $current_page > 4) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            } elseif ($i == $total_pages - 2 && $current_page < $total_pages - 3) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        if ($current_page < $total_pages) {
            $html .= '<li class="page-item"><a class="page-link" href="?page=csv-import-module&paged=' . ($current_page + 1) . '">Next ></a></li>';
            $html .= '<li class="page-item"><a class="page-link" href="?page=csv-import-module&paged=' . $total_pages . '">Last >></a></li>';
        }
        $html .= '</ul></nav>';
        $html .= '</div>';

        wp_send_json_success($html); // Return the table as a JSON success response
    } else {
        wp_send_json_error('No data found for this user.');
    }

    wp_die(); // Properly terminate the AJAX request
}
add_action('wp_ajax_fetch_user_data', 'fetch_user_data'); // For logged-in users
?>