<?php
/**
 * Script thực thi migration để vá các lỗi database audit
 * Chạy: php run_migration.php
 */

// Load CodeIgniter environment
define('BASEPATH', true);
require_once(__DIR__ . '/../../../application/config/app-config.php');

// Kết nối MySQL
$mysqli = new mysqli(
    APP_DB_HOSTNAME,
    APP_DB_USERNAME,
    APP_DB_PASSWORD,
    APP_DB_NAME
);

if ($mysqli->connect_error) {
    die("❌ KHÔNG KẾT NỐI ĐƯỢC DATABASE: " . $mysqli->connect_error . "\n");
}

echo "✅ Đã kết nối database: " . APP_DB_NAME . "\n\n";

// Đọc file SQL migration
$sql_file = __DIR__ . '/001_fix_audit_issues.sql';
if (!file_exists($sql_file)) {
    die("❌ Không tìm thấy file migration: $sql_file\n");
}

$sql_content = file_get_contents($sql_file);

// Tách các câu lệnh SQL (bỏ qua comment)
$statements = [];
$current_statement = '';
$lines = explode("\n", $sql_content);

foreach ($lines as $line) {
    $line = trim($line);
    
    // Bỏ qua comment và dòng trống
    if (empty($line) || strpos($line, '--') === 0) {
        continue;
    }
    
    $current_statement .= ' ' . $line;
    
    // Nếu gặp dấu ; thì kết thúc statement
    if (substr($line, -1) === ';') {
        $statements[] = trim($current_statement);
        $current_statement = '';
    }
}

echo "📋 Tìm thấy " . count($statements) . " câu lệnh SQL\n\n";
echo "🔧 BẮT ĐẦU THỰC THI MIGRATION...\n";
echo str_repeat('=', 70) . "\n\n";

$success_count = 0;
$error_count = 0;

foreach ($statements as $index => $sql) {
    $stmt_num = $index + 1;
    $sql = trim($sql);
    
    if (empty($sql)) {
        continue;
    }
    
    echo "[$stmt_num] Thực thi: " . substr($sql, 0, 80) . "...\n";
    
    if ($mysqli->multi_query($sql)) {
        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->next_result());
        
        echo "    ✅ Thành công\n\n";
        $success_count++;
    } else {
        $error = $mysqli->error;
        
        // Bỏ qua lỗi "Duplicate column" hoặc "Duplicate key" (cột/index đã tồn tại)
        if (strpos($error, 'Duplicate column') !== false || 
            strpos($error, 'Duplicate key') !== false) {
            echo "    ⚠️  Đã tồn tại, bỏ qua: $error\n\n";
            $success_count++;
        } else {
            echo "    ❌ LỖI: $error\n\n";
            $error_count++;
        }
    }
}

echo str_repeat('=', 70) . "\n";
echo "📊 KẾT QUẢ MIGRATION:\n";
echo "   - Thành công: $success_count\n";
echo "   - Lỗi: $error_count\n\n";

// Verification: Kiểm tra các cột đã được thêm chưa
echo "🔍 KIỂM TRA KẾT QUẢ...\n";
echo str_repeat('=', 70) . "\n\n";

// Check tblsales_pipeline
$check_columns = ['notes', 'reporting_period', 'import_batch_id', 'imported_by'];
$query = "SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT 
          FROM INFORMATION_SCHEMA.COLUMNS 
          WHERE TABLE_SCHEMA = '" . APP_DB_NAME . "' 
            AND TABLE_NAME = 'tblsales_pipeline'
            AND COLUMN_NAME IN ('" . implode("','", $check_columns) . "')";

$result = $mysqli->query($query);
$found_columns = [];

if ($result) {
    echo "✅ Bảng tblsales_pipeline:\n";
    while ($row = $result->fetch_assoc()) {
        echo "   - {$row['COLUMN_NAME']} ({$row['COLUMN_TYPE']}): {$row['COLUMN_COMMENT']}\n";
        $found_columns[] = $row['COLUMN_NAME'];
    }
    
    $missing = array_diff($check_columns, $found_columns);
    if (empty($missing)) {
        echo "   ✅ Tất cả cột cần thiết đã có!\n\n";
    } else {
        echo "   ⚠️  Thiếu cột: " . implode(', ', $missing) . "\n\n";
    }
}

// Check tblsales_pipeline_activity
$check_activity_columns = ['field_changed', 'old_value', 'new_value'];
$query = "SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT 
          FROM INFORMATION_SCHEMA.COLUMNS 
          WHERE TABLE_SCHEMA = '" . APP_DB_NAME . "' 
            AND TABLE_NAME = 'tblsales_pipeline_activity'
            AND COLUMN_NAME IN ('" . implode("','", $check_activity_columns) . "')";

$result = $mysqli->query($query);
$found_activity_columns = [];

if ($result) {
    echo "✅ Bảng tblsales_pipeline_activity:\n";
    while ($row = $result->fetch_assoc()) {
        echo "   - {$row['COLUMN_NAME']} ({$row['COLUMN_TYPE']}): {$row['COLUMN_COMMENT']}\n";
        $found_activity_columns[] = $row['COLUMN_NAME'];
    }
    
    $missing = array_diff($check_activity_columns, $found_activity_columns);
    if (empty($missing)) {
        echo "   ✅ Tất cả cột cần thiết đã có!\n\n";
    } else {
        echo "   ⚠️  Thiếu cột: " . implode(', ', $missing) . "\n\n";
    }
}

echo str_repeat('=', 70) . "\n";

if ($error_count === 0) {
    echo "🎉 MIGRATION HOÀN TẤT THÀNH CÔNG!\n";
    echo "✅ Đã vá tất cả các lỗi database từ audit report.\n\n";
} else {
    echo "⚠️  MIGRATION HOÀN TẤT VỚI $error_count LỖI\n";
    echo "Vui lòng kiểm tra log bên trên để khắc phục.\n\n";
}

$mysqli->close();
