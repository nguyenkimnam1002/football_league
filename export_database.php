<?php 
// =============================================================================
// COMPLETE EXPORT DATABASE FOR FOOTBALL LEAGUE
// =============================================================================

$SECRET_KEY = "fcgagay01072025"; // Đổi key này

if (!isset($_GET['key']) || $_GET['key'] !== $SECRET_KEY) {
    die("Access denied!");
}

require_once 'config.php';

$pdo = DB::getInstance();
$action = $_GET['action'] ?? 'menu';
$table = $_GET['table'] ?? '';
$format = $_GET['format'] ?? 'sql';

switch ($action) {
    case 'sql':
        exportToSQL();
        break;
    case 'csv':
        exportToCSV($table);
        break;
    case 'json':
        exportToJSON($table);
        break;
    case 'excel':
        exportToExcel($table);
        break;
    case 'all_csv':
        exportAllTablesCSV();
        break;
    default:
        showExportMenu();
}

function showExportMenu() {
    global $SECRET_KEY;
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>🏆 Football League Database Export</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
            .container { background: white; padding: 30px; border-radius: 10px; max-width: 800px; }
            .export-option { 
                display: inline-block; 
                margin: 10px; 
                padding: 15px 20px; 
                background: #007cba; 
                color: white; 
                text-decoration: none; 
                border-radius: 5px;
                transition: background 0.3s;
            }
            .export-option:hover { background: #005a87; color: white; text-decoration: none; }
            .csv-option { background: #28a745; }
            .json-option { background: #ffc107; color: black; }
            .excel-option { background: #17a2b8; }
            h1 { color: #333; }
            .tables { margin: 20px 0; }
            .table-list { background: #f8f9fa; padding: 15px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🏆 Football League Database Export</h1>
            
            <h2>📊 Export Toàn Bộ Database</h2>
            <a href="?key=<?= $SECRET_KEY ?>&action=sql" class="export-option">
                📄 Download SQL File (.sql)
            </a>
            <a href="?key=<?= $SECRET_KEY ?>&action=all_csv" class="export-option csv-option">
                📊 Download All CSV Files (.zip)
            </a>
            
            <h2>📋 Export Từng Bảng</h2>
            <div class="tables">
                <?php
                global $pdo;
                $tables = ['players', 'daily_matches', 'match_participants', 'daily_registrations', 'player_stats', 'system_config'];
                
                foreach ($tables as $table) {
                    echo "<div class='table-list'>";
                    echo "<h3>🗃️ Bảng: $table</h3>";
                    echo "<a href='?key=$SECRET_KEY&action=csv&table=$table' class='export-option csv-option'>CSV</a>";
                    echo "<a href='?key=$SECRET_KEY&action=json&table=$table' class='export-option json-option'>JSON</a>";
                    echo "<a href='?key=$SECRET_KEY&action=excel&table=$table' class='export-option excel-option'>Excel</a>";
                    echo "</div>";
                }
                ?>
            </div>
            
            <h2>ℹ️ Hướng Dẫn</h2>
            <ul>
                <li><strong>SQL:</strong> File backup đầy đủ, có thể import lại</li>
                <li><strong>CSV:</strong> Dễ đọc bằng Excel, Google Sheets</li>
                <li><strong>JSON:</strong> Dùng cho lập trình, API</li>
                <li><strong>Excel:</strong> File .xlsx cho Microsoft Excel</li>
            </ul>
        </div>
    </body>
    </html>
    <?php
}

// =============================================================================
// EXPORT TO SQL FUNCTION
// =============================================================================

function exportToSQL() {
    global $pdo;
    
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "football_league_$timestamp.sql";
    
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');
    
    echo "-- =====================================================\n";
    echo "-- Football League Database Export\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- =====================================================\n\n";
    
    echo "SET FOREIGN_KEY_CHECKS=0;\n";
    echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    echo "SET AUTOCOMMIT = 0;\n";
    echo "START TRANSACTION;\n\n";
    
    $tables = ['players', 'daily_matches', 'match_participants', 'daily_registrations', 'player_stats', 'system_config'];
    
    foreach ($tables as $table) {
        exportTableSQL($table);
    }
    
    echo "\nSET FOREIGN_KEY_CHECKS=1;\n";
    echo "COMMIT;\n";
    echo "-- Export completed successfully!\n";
}

function exportTableSQL($table) {
    global $pdo;
    
    try {
        // Check if table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            echo "-- Table $table does not exist\n\n";
            return;
        }
        
        echo "-- =====================================================\n";
        echo "-- Table: $table\n";
        echo "-- =====================================================\n\n";
        
        // Drop table
        echo "DROP TABLE IF EXISTS `$table`;\n\n";
        
        // Create table
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        if ($row = $stmt->fetch()) {
            echo $row[1] . ";\n\n";
        }
        
        // Insert data
        echo "-- Dumping data for table `$table`\n\n";
        $stmt = $pdo->query("SELECT * FROM `$table`");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns = "`" . implode("`, `", array_keys($row)) . "`";
            
            $values = array_map(function($value) use ($pdo) {
                if ($value === null) {
                    return 'NULL';
                } else {
                    return $pdo->quote($value);
                }
            }, array_values($row));
            
            echo "INSERT INTO `$table` ($columns) VALUES (" . implode(", ", $values) . ");\n";
        }
        
        echo "\n";
        
    } catch (Exception $e) {
        echo "-- Error exporting table $table: " . $e->getMessage() . "\n\n";
    }
}

// =============================================================================
// EXPORT TO CSV FUNCTION
// =============================================================================

function exportToCSV($table) {
    global $pdo;
    
    if (empty($table)) {
        die("Table name required!");
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "{$table}_{$timestamp}.csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');
    
    // BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    try {
        // Check if table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            fputcsv($output, ["Error: Table $table does not exist"]);
            fclose($output);
            return;
        }
        
        $stmt = $pdo->query("SELECT * FROM `$table`");
        
        // Write headers
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, array_keys($row));
            fputcsv($output, array_values($row));
            
            // Write data
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, array_values($row));
            }
        } else {
            fputcsv($output, ["No data found in table $table"]);
        }
        
    } catch (Exception $e) {
        fputcsv($output, ["Error: " . $e->getMessage()]);
    }
    
    fclose($output);
}

// =============================================================================
// EXPORT TO JSON FUNCTION
// =============================================================================

function exportToJSON($table) {
    global $pdo;
    
    if (empty($table)) {
        die("Table name required!");
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "{$table}_{$timestamp}.json";
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');
    
    try {
        // Check if table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => "Table $table does not exist"]);
            return;
        }
        
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $export = [
            'table' => $table,
            'exported_at' => date('Y-m-d H:i:s'),
            'total_records' => count($data),
            'data' => $data
        ];
        
        echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// =============================================================================
// EXPORT TO EXCEL FUNCTION
// =============================================================================

function exportToExcel($table) {
    global $pdo;
    
    if (empty($table)) {
        die("Table name required!");
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "{$table}_{$timestamp}.xls";
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');
    
    echo "\xEF\xBB\xBF"; // BOM
    
    try {
        // Check if table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            echo "<p>Error: Table $table does not exist</p>";
            return;
        }
        
        $stmt = $pdo->query("SELECT * FROM `$table`");
        
        echo "<table border='1'>\n";
        
        // Headers
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            foreach (array_keys($row) as $header) {
                echo "<th>" . htmlspecialchars($header) . "</th>";
            }
            echo "</tr>\n";
            
            // First row data
            echo "<tr>";
            foreach (array_values($row) as $value) {
                echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
            }
            echo "</tr>\n";
            
            // Remaining rows
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                foreach (array_values($row) as $value) {
                    echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
                }
                echo "</tr>\n";
            }
        } else {
            echo "<tr><td>No data found in table $table</td></tr>";
        }
        
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// =============================================================================
// EXPORT ALL TABLES TO CSV (ZIP)
// =============================================================================

function exportAllTablesCSV() {
    global $pdo;
    
    $timestamp = date('Y-m-d_H-i-s');
    $zipFile = "football_league_csv_$timestamp.zip";
    
    // Check if ZipArchive is available
    if (!class_exists('ZipArchive')) {
        // Fallback: Export as single CSV file
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="football_league_all_' . $timestamp . '.csv"');
        echo "\xEF\xBB\xBF"; // BOM
        
        $tables = ['players', 'daily_matches', 'match_participants', 'daily_registrations', 'player_stats', 'system_config'];
        
        foreach ($tables as $table) {
            echo "\n=== TABLE: $table ===\n";
            
            try {
                $stmt = $pdo->query("SELECT * FROM `$table`");
                
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    // Headers
                    echo '"' . implode('","', array_keys($row)) . '"' . "\n";
                    
                    // First row
                    $values = array_map(function($v) { return str_replace('"', '""', $v ?? ''); }, array_values($row));
                    echo '"' . implode('","', $values) . '"' . "\n";
                    
                    // Remaining rows
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $values = array_map(function($v) { return str_replace('"', '""', $v ?? ''); }, array_values($row));
                        echo '"' . implode('","', $values) . '"' . "\n";
                    }
                }
            } catch (Exception $e) {
                echo "Error in table $table: " . $e->getMessage() . "\n";
            }
            
            echo "\n";
        }
        return;
    }
    
    // Create ZIP file
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
        die("Cannot create zip file!");
    }
    
    $tables = ['players', 'daily_matches', 'match_participants', 'daily_registrations', 'player_stats', 'system_config'];
    
    foreach ($tables as $table) {
        try {
            // Check if table exists
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if (!$stmt->fetch()) {
                $zip->addFromString("$table.error.txt", "Table $table does not exist");
                continue;
            }
            
            $csvContent = "\xEF\xBB\xBF"; // BOM
            $stmt = $pdo->query("SELECT * FROM `$table`");
            
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Headers
                $csvContent .= '"' . implode('","', array_keys($row)) . '"' . "\n";
                
                // First row
                $values = array_map(function($v) { return str_replace('"', '""', $v ?? ''); }, array_values($row));
                $csvContent .= '"' . implode('","', $values) . '"' . "\n";
                
                // Remaining rows
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $values = array_map(function($v) { return str_replace('"', '""', $v ?? ''); }, array_values($row));
                    $csvContent .= '"' . implode('","', $values) . '"' . "\n";
                }
            } else {
                $csvContent .= "No data found in table $table\n";
            }
            
            $zip->addFromString("$table.csv", $csvContent);
            
        } catch (Exception $e) {
            $zip->addFromString("$table.error.txt", "Error: " . $e->getMessage());
        }
    }
    
    // Add export info
    $info = "Football League Database Export\n";
    $info .= "Generated: " . date('Y-m-d H:i:s') . "\n";
    $info .= "Tables included: " . implode(', ', $tables) . "\n";
    $info .= "Total files: " . count($tables) . "\n";
    $zip->addFromString("export_info.txt", $info);
    
    $zip->close();
    
    // Output ZIP file
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFile . '"');
    header('Content-Length: ' . filesize($zipFile));
    
    readfile($zipFile);
    unlink($zipFile); // Delete temporary file
}

?>