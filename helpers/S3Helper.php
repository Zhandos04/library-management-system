<?php
/**
 * AWS S3 Helper Class
 * Manages image upload to AWS S3 bucket
 */
class S3Helper {
    private $s3Client;
    private $bucket;
    private $useS3 = false;
    
    public function __construct() {
        error_log("[S3Helper] Initializing...");
        
        $this->bucket = getenv('AWS_S3_BUCKET') ?: 'library-system-bucket';
        error_log("[S3Helper] S3 Bucket: " . $this->bucket);
        
        // Check if AWS SDK is available
        if (file_exists('vendor/autoload.php')) {
            error_log("[S3Helper] AWS SDK found (vendor/autoload.php exists)");
            try {
                require_once 'vendor/autoload.php';
                
                $awsKeyId = getenv('AWS_ACCESS_KEY_ID');
                $awsSecret = getenv('AWS_SECRET_ACCESS_KEY');
                $awsRegion = getenv('AWS_REGION') ?: 'us-east-1';
                
                error_log("[S3Helper] AWS Key ID present: " . (!empty($awsKeyId) ? 'Yes' : 'No'));
                error_log("[S3Helper] AWS Secret present: " . (!empty($awsSecret) ? 'Yes' : 'No'));
                error_log("[S3Helper] AWS Region: " . $awsRegion);
                
                // Check if AWS credentials are set
                if (!empty($awsKeyId) && !empty($awsSecret)) {
                    // Initialize S3 client
                    error_log("[S3Helper] Initializing S3 client...");
                    $this->s3Client = new Aws\S3\S3Client([
                        'version' => 'latest',
                        'region' => $awsRegion,
                        'credentials' => [
                            'key'    => $awsKeyId,
                            'secret' => $awsSecret,
                        ],
                    ]);
                    
                    // Verify connection by listing buckets
                    try {
                        error_log("[S3Helper] Testing S3 connection by listing buckets...");
                        $buckets = $this->s3Client->listBuckets();
                        $bucketList = [];
                        foreach ($buckets['Buckets'] as $bucket) {
                            $bucketList[] = $bucket['Name'];
                        }
                        error_log("[S3Helper] Available buckets: " . implode(', ', $bucketList));
                        
                        // Check if our bucket exists
                        $bucketExists = in_array($this->bucket, $bucketList);
                        error_log("[S3Helper] Configured bucket '{$this->bucket}' exists: " . ($bucketExists ? 'Yes' : 'No'));
                        
                        $this->useS3 = true;
                        error_log("[S3Helper] S3 connection successful, AWS S3 will be used for storage");
                    } catch (Exception $e) {
                        error_log("[S3Helper] ERROR testing S3 connection: " . $e->getMessage());
                        error_log("[S3Helper] Falling back to local storage");
                    }
                } else {
                    error_log("[S3Helper] AWS credentials not set. Using local storage instead.");
                }
            } catch (Exception $e) {
                error_log("[S3Helper] AWS SDK initialization error: " . $e->getMessage());
                error_log("[S3Helper] Stack trace: " . $e->getTraceAsString());
            }
        } else {
            error_log("[S3Helper] AWS SDK not found (vendor/autoload.php missing). Using local storage instead.");
        }
        
        error_log("[S3Helper] Initialization complete. Using S3: " . ($this->useS3 ? 'Yes' : 'No'));
    }
    
    /**
     * Upload an image to S3 or local storage
     * 
     * @param array $file $_FILES array element
     * @param string $destinationPath Path to store in S3 or local storage
     * @return string|false The URL of the uploaded image or false on failure
     */
    public function uploadImage($file, $destinationPath) {
        error_log("[S3Helper] Upload request received for path: " . $destinationPath);
        error_log("[S3Helper] File info: " . print_r($file, true));
        
        // Check if file exists and has no errors
        if (!isset($file) || $file['error'] !== 0) {
            error_log("[S3Helper] ERROR: File not provided or has errors. Error code: " . 
                     (isset($file) ? $file['error'] : 'File not set'));
            return false;
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!in_array($file['type'], $allowedTypes)) {
            error_log("[S3Helper] ERROR: Invalid file type: " . $file['type'] . 
                     ". Allowed types: " . implode(', ', $allowedTypes));
            return false;
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        error_log("[S3Helper] Generated filename: " . $filename);
        
        // If S3 is available, upload to S3
        if ($this->useS3) {
            error_log("[S3Helper] Attempting S3 upload...");
            $key = $destinationPath . '/' . $filename;
            error_log("[S3Helper] S3 object key: " . $key);
            
            try {
                // Upload to S3
                error_log("[S3Helper] Uploading to S3 bucket: " . $this->bucket);
                $result = $this->s3Client->putObject([
                    'Bucket' => $this->bucket,
                    'Key'    => $key,
                    'Body'   => fopen($file['tmp_name'], 'rb'),
                    'ContentType' => $file['type']
                ]);
                
                // Return the URL
                $url = $result['ObjectURL'];
                error_log("[S3Helper] S3 upload successful. URL: " . $url);
                return $url;
            } catch (Exception $e) {
                error_log("[S3Helper] S3 Upload Error: " . $e->getMessage());
                error_log("[S3Helper] Stack trace: " . $e->getTraceAsString());
                error_log("[S3Helper] Falling back to local storage");
            }
        } else {
            error_log("[S3Helper] S3 not available, using local storage");
        }
        
        // Fallback to local storage
        $localPath = 'uploads/' . $destinationPath;
        if (!is_dir($localPath)) {
            error_log("[S3Helper] Creating directory: " . $localPath);
            $mkdirResult = mkdir($localPath, 0777, true);
            error_log("[S3Helper] Directory creation result: " . ($mkdirResult ? 'Success' : 'Failed'));
        } else {
            error_log("[S3Helper] Directory already exists: " . $localPath);
        }
        
        $localFilePath = $localPath . '/' . $filename;
        error_log("[S3Helper] Moving uploaded file to: " . $localFilePath);
        
        if (move_uploaded_file($file['tmp_name'], $localFilePath)) {
            error_log("[S3Helper] Local file upload successful");
            // Return local URL
            return $localFilePath;
        } else {
            error_log("[S3Helper] ERROR: Failed to move uploaded file. Check permissions.");
            error_log("[S3Helper] Tmp file: " . $file['tmp_name']);
            error_log("[S3Helper] Destination: " . $localFilePath);
            error_log("[S3Helper] File permissions: " . substr(sprintf('%o', fileperms($file['tmp_name'])), -4));
            error_log("[S3Helper] Dir permissions: " . substr(sprintf('%o', fileperms($localPath)), -4));
        }
        
        error_log("[S3Helper] File upload failed completely");
        return false;
    }
    
    /**
     * Delete an image from S3 or local storage
     * 
     * @param string $imageUrl The URL of the image to delete
     * @return bool Success or failure
     */
    public function deleteImage($imageUrl) {
        error_log("[S3Helper] Delete request for image: " . $imageUrl);
        
        // Check if it's a local file
        if (file_exists($imageUrl)) {
            error_log("[S3Helper] File exists locally, attempting to delete");
            $result = unlink($imageUrl);
            error_log("[S3Helper] Local delete result: " . ($result ? 'Success' : 'Failed'));
            return $result;
        } else {
            error_log("[S3Helper] File does not exist locally");
        }
        
        // Otherwise, try to delete from S3
        if (!$this->useS3) {
            error_log("[S3Helper] S3 not available, cannot delete from S3");
            return false;
        }
        
        error_log("[S3Helper] Attempting to delete from S3");
        
        // Extract the key from the URL
        $parsedUrl = parse_url($imageUrl);
        $path = $parsedUrl['path'];
        $key = ltrim($path, '/');
        error_log("[S3Helper] Parsed path from URL: " . $path);
        error_log("[S3Helper] Initial key: " . $key);
        
        // If the bucket name is in the path, remove it
        if (strpos($key, $this->bucket . '/') === 0) {
            $key = substr($key, strlen($this->bucket) + 1);
            error_log("[S3Helper] Removed bucket name from key: " . $key);
        }
        
        try {
            // Delete from S3
            error_log("[S3Helper] Deleting object from bucket: " . $this->bucket . ", key: " . $key);
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $key
            ]);
            
            error_log("[S3Helper] S3 delete successful");
            return true;
        } catch (Exception $e) {
            error_log("[S3Helper] S3 Delete Error: " . $e->getMessage());
            error_log("[S3Helper] Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
}