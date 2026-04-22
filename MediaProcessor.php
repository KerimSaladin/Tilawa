<?php
/**
 * MediaProcessor Class
 * Handles audio and video file conversion using FFmpeg
 */

class MediaProcessor {
    private $ffmpegPath;
    private $ffprobePath;
    private $uploadDir;
    
    public function __construct($uploadDir = null) {
        $this->uploadDir = $uploadDir ?? UPLOAD_DIR;
        
        // Try to locate FFmpeg automatically
        $this->ffmpegPath = $this->findFFmpeg();
        $this->ffprobePath = $this->findFFprobe();
    }
    
    /**
     * Find FFmpeg binary path
     */
    private function findFFmpeg() {
        // Check if defined in config
        if (defined('FFMPEG_PATH') && !empty(FFMPEG_PATH)) {
            return FFMPEG_PATH;
        }
        
        // Try common Windows locations
        $commonPaths = [
            'ffmpeg',  // System PATH
            'C:\\ffmpeg\\bin\\ffmpeg.exe',
            'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe',
            dirname(__DIR__) . '\\ffmpeg\\bin\\ffmpeg.exe',
        ];
        
        foreach ($commonPaths as $path) {
            if ($this->commandExists($path)) {
                return $path;
            }
        }
        
        return 'ffmpeg'; // Fallback to system PATH
    }
    
    /**
     * Find FFprobe binary path
     */
    private function findFFprobe() {
        // Check if defined in config
        if (defined('FFPROBE_PATH') && !empty(FFPROBE_PATH)) {
            return FFPROBE_PATH;
        }
        
        // Try common Windows locations
        $commonPaths = [
            'ffprobe',  // System PATH
            'C:\\ffmpeg\\bin\\ffprobe.exe',
            'C:\\Program Files\\ffmpeg\\bin\\ffprobe.exe',
            dirname(__DIR__) . '\\ffmpeg\\bin\\ffprobe.exe',
        ];
        
        foreach ($commonPaths as $path) {
            if ($this->commandExists($path)) {
                return $path;
            }
        }
        
        return 'ffprobe'; // Fallback to system PATH
    }
    
    /**
     * Check if a command exists
     */
    private function commandExists($command) {
        $command = escapeshellarg($command);
        $output = shell_exec("where $command 2>nul");
        return !empty($output);
    }
    
    /**
     * Check if FFmpeg is available
     */
    public function isAvailable() {
        $output = [];
        $returnVar = 0;
        exec(escapeshellarg($this->ffmpegPath) . " -version 2>&1", $output, $returnVar);
        return $returnVar === 0;
    }
    
    /**
     * Get media file information
     */
    public function getMediaInfo($filepath) {
        if (!file_exists($filepath)) {
            return false;
        }
        
        $command = escapeshellarg($this->ffprobePath) . 
                   " -v quiet -print_format json -show_format -show_streams " . 
                   escapeshellarg($filepath);
        
        $output = shell_exec($command);
        return json_decode($output, true);
    }
    
    /**
     * Convert WebM audio to MP3
     * 
     * @param string $inputPath Full path to input WebM file
     * @param string $outputFilename Desired output filename (optional)
     * @return array ['success' => bool, 'outputPath' => string, 'message' => string]
     */
    public function convertAudioToMP3($inputPath, $outputFilename = null) {
        if (!file_exists($inputPath)) {
            return ['success' => false, 'message' => 'Input file not found'];
        }
        
        if (!$this->isAvailable()) {
            return ['success' => false, 'message' => 'FFmpeg is not available'];
        }
        
        // Generate output filename if not provided
        if ($outputFilename === null) {
            $pathInfo = pathinfo($inputPath);
            $outputFilename = $pathInfo['filename'] . '.mp3';
        }
        
        $outputPath = $this->uploadDir . $outputFilename;
        
        // FFmpeg command for audio conversion
        // -i: input file
        // -vn: no video
        // -ar: audio sample rate (44100 Hz)
        // -ac: audio channels (2 for stereo)
        // -b:a: audio bitrate (128k for good quality)
        // -y: overwrite output file
        $command = sprintf(
            '%s -i %s -vn -ar 44100 -ac 2 -b:a 128k -y %s 2>&1',
            escapeshellarg($this->ffmpegPath),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );
        
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            return [
                'success' => false,
                'message' => 'FFmpeg conversion failed: ' . implode("\n", $output)
            ];
        }
        
        if (!file_exists($outputPath) || filesize($outputPath) === 0) {
            return [
                'success' => false,
                'message' => 'Output file was not created or is empty'
            ];
        }
        
        return [
            'success' => true,
            'outputPath' => $outputPath,
            'outputFilename' => $outputFilename,
            'message' => 'Audio converted successfully'
        ];
    }
    
    /**
     * Convert WebM video to MP4
     * 
     * @param string $inputPath Full path to input WebM file
     * @param string $outputFilename Desired output filename (optional)
     * @return array ['success' => bool, 'outputPath' => string, 'message' => string]
     */
    public function convertVideoToMP4($inputPath, $outputFilename = null) {
        if (!file_exists($inputPath)) {
            return ['success' => false, 'message' => 'Input file not found'];
        }
        
        if (!$this->isAvailable()) {
            return ['success' => false, 'message' => 'FFmpeg is not available'];
        }
        
        // Generate output filename if not provided
        if ($outputFilename === null) {
            $pathInfo = pathinfo($inputPath);
            $outputFilename = $pathInfo['filename'] . '.mp4';
        }
        
        $outputPath = $this->uploadDir . $outputFilename;
        
        // FFmpeg command for video conversion
        // -i: input file
        // -c:v libx264: video codec H.264
        // -preset fast: encoding speed/quality tradeoff
        // -crf 23: quality (0-51, lower is better, 23 is default)
        // -c:a aac: audio codec AAC
        // -b:a 128k: audio bitrate
        // -movflags +faststart: optimize for web streaming
        // -y: overwrite output file
        $command = sprintf(
            '%s -i %s -c:v libx264 -preset fast -crf 23 -c:a aac -b:a 128k -movflags +faststart -y %s 2>&1',
            escapeshellarg($this->ffmpegPath),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );
        
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            return [
                'success' => false,
                'message' => 'FFmpeg conversion failed: ' . implode("\n", $output)
            ];
        }
        
        if (!file_exists($outputPath) || filesize($outputPath) === 0) {
            return [
                'success' => false,
                'message' => 'Output file was not created or is empty'
            ];
        }
        
        return [
            'success' => true,
            'outputPath' => $outputPath,
            'outputFilename' => $outputFilename,
            'message' => 'Video converted successfully'
        ];
    }
    
    /**
     * Process uploaded media file (auto-detect type and convert)
     * 
     * @param string $inputPath Full path to input file
     * @return array ['success' => bool, 'outputPath' => string, 'outputFilename' => string, 'type' => string, 'message' => string]
     */
    public function processMediaFile($inputPath) {
        if (!file_exists($inputPath)) {
            return ['success' => false, 'message' => 'Input file not found'];
        }
        
        // Get media info to determine type
        $mediaInfo = $this->getMediaInfo($inputPath);
        
        if (!$mediaInfo) {
            // Fallback: check file extension
            $extension = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
            $isVideo = in_array($extension, ['webm', 'mp4', 'mov', 'avi']);
            
            if ($isVideo) {
                return $this->convertVideoToMP4($inputPath);
            } else {
                return $this->convertAudioToMP3($inputPath);
            }
        }
        
        // Check if file has video streams
        $hasVideo = false;
        if (isset($mediaInfo['streams'])) {
            foreach ($mediaInfo['streams'] as $stream) {
                if (isset($stream['codec_type']) && $stream['codec_type'] === 'video') {
                    $hasVideo = true;
                    break;
                }
            }
        }
        
        // Convert based on content
        if ($hasVideo) {
            $result = $this->convertVideoToMP4($inputPath);
            $result['type'] = 'video';
        } else {
            $result = $this->convertAudioToMP3($inputPath);
            $result['type'] = 'audio';
        }
        
        return $result;
    }
    
    /**
     * Delete temporary file
     */
    public function deleteTempFile($filepath) {
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        return false;
    }
}
?>
