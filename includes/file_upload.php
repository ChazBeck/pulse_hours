<?php
/**
 * File Upload Handler
 * 
 * Centralized and secure file upload handling for the PluseHours application.
 * Provides validation, security checks, and standardized upload processing.
 */

/**
 * Handle file upload with security validation
 * 
 * @param array $file The $_FILES array element for the uploaded file
 * @param string $upload_dir Directory path where file should be uploaded (with trailing slash)
 * @param array $allowed_extensions Array of allowed file extensions (e.g., ['jpg', 'png', 'gif'])
 * @param int $max_size_bytes Maximum file size in bytes
 * @param string $file_prefix Prefix for the generated filename (default: 'upload_')
 * @return array ['success' => bool, 'filename' => string|null, 'path' => string|null, 'error' => string|null]
 */
function handle_file_upload($file, $upload_dir, $allowed_extensions, $max_size_bytes, $file_prefix = 'upload_') {
    $result = [
        'success' => false,
        'filename' => null,
        'path' => null,
        'error' => null
    ];
    
    // Check if file was uploaded
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $result['error'] = 'No file uploaded';
        return $result;
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $result['error'] = 'File is too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $result['error'] = 'File was only partially uploaded';
                break;
            default:
                $result['error'] = 'File upload error occurred';
        }
        return $result;
    }
    
    // Validate file size
    if ($file['size'] > $max_size_bytes) {
        $result['error'] = 'File exceeds maximum size of ' . ($max_size_bytes / 1024 / 1024) . 'MB';
        return $result;
    }
    
    // Validate file extension
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_extensions)) {
        $result['error'] = 'Invalid file type. Allowed types: ' . implode(', ', $allowed_extensions);
        return $result;
    }
    
    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp'
    ];
    
    if (isset($allowed_mimes[$file_ext]) && $mime_type !== $allowed_mimes[$file_ext]) {
        $result['error'] = 'File type does not match its extension';
        return $result;
    }
    
    // Create upload directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $result['error'] = 'Failed to create upload directory';
            return $result;
        }
    }
    
    // Generate unique filename
    $unique_filename = uniqid($file_prefix) . '.' . $file_ext;
    $target_path = $upload_dir . $unique_filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $result['success'] = true;
        $result['filename'] = $unique_filename;
        $result['path'] = $target_path;
    } else {
        $result['error'] = 'Failed to move uploaded file';
    }
    
    return $result;
}

/**
 * Handle logo upload specifically for clients
 * Wrapper function with predefined settings for logo uploads
 * 
 * @param array $file The $_FILES array element for the uploaded logo
 * @return array ['success' => bool, 'relative_path' => string|null, 'error' => string|null]
 */
function handle_logo_upload($file) {
    $upload_dir = __DIR__ . '/../uploads/logos/';
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
    $max_size_bytes = 5 * 1024 * 1024; // 5MB
    
    $result = handle_file_upload($file, $upload_dir, $allowed_extensions, $max_size_bytes, 'logo_');
    
    if ($result['success']) {
        // Return relative path for database storage
        return [
            'success' => true,
            'relative_path' => 'uploads/logos/' . $result['filename'],
            'error' => null
        ];
    }
    
    return [
        'success' => false,
        'relative_path' => null,
        'error' => $result['error']
    ];
}

/**
 * Delete uploaded file
 * 
 * @param string $file_path Relative or absolute path to the file
 * @return bool True if file was deleted, false otherwise
 */
function delete_uploaded_file($file_path) {
    // Convert relative path to absolute
    if (!file_exists($file_path)) {
        $absolute_path = __DIR__ . '/../' . $file_path;
    } else {
        $absolute_path = $file_path;
    }
    
    if (file_exists($absolute_path) && is_file($absolute_path)) {
        return unlink($absolute_path);
    }
    
    return false;
}
