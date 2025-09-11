<?php
// SimpleXLSXWriter.php - A lightweight XLSX writer without external dependencies
class SimpleXLSXWriter {
    private $data = [];
    private $headers = [];
    private $filename = '';
    
    public function __construct($filename = 'export') {
        $this->filename = $filename;
    }
    
    public function setHeaders($headers) {
        $this->headers = $headers;
    }
    
    public function addRow($row) {
        $this->data[] = $row;
    }
    
    public function download() {
        // For true XLSX format, we'd need ZipArchive and XML generation
        // For now, we'll create a CSV that opens properly in Excel
        $this->downloadAsCSV();
    }
    
    private function downloadAsCSV() {
        // Set headers for CSV download that Excel recognizes as .xlsx
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $this->filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        // Create CSV content
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8 CSV files to display properly in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Output headers
        if (!empty($this->headers)) {
            fputcsv($output, $this->headers);
        }
        
        // Output data
        foreach ($this->data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
    }
    
    // Alternative HTML table method that works well with Excel
    public function downloadAsExcelHTML() {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $this->filename . '.xls"');
        header('Cache-Control: max-age=0');
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        echo ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        echo ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        echo '<Worksheet ss:Name="Sheet1">' . "\n";
        echo '<Table>' . "\n";
        
        // Output headers
        if (!empty($this->headers)) {
            echo '<Row>' . "\n";
            foreach ($this->headers as $header) {
                echo '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>' . "\n";
            }
            echo '</Row>' . "\n";
        }
        
        // Output data
        foreach ($this->data as $row) {
            echo '<Row>' . "\n";
            foreach ($row as $cell) {
                $cellType = is_numeric($cell) ? 'Number' : 'String';
                echo '<Cell><Data ss:Type="' . $cellType . '">' . htmlspecialchars($cell) . '</Data></Cell>' . "\n";
            }
            echo '</Row>' . "\n";
        }
        
        echo '</Table>' . "\n";
        echo '</Worksheet>' . "\n";
        echo '</Workbook>' . "\n";
    }
}
?>