# FFmpeg Installation Guide for Tilawa Platform

This guide will help you install and configure FFmpeg for automatic audio/video conversion.

## Why FFmpeg?

The platform now uses FFmpeg to convert browser-recorded audio and video files:
- **Audio**: WebM → MP3 (better browser compatibility)
- **Video**: WebM → MP4 (H.264, universal playback)

This fixes rendering and playback issues across different browsers and devices.

## Installation Steps (Windows/XAMPP)

### Step 1: Download FFmpeg

1. Visit [https://www.gyan.dev/ffmpeg/builds/](https://www.gyan.dev/ffmpeg/builds/)
2. Download **ffmpeg-release-essentials.zip**
3. Extract the ZIP file

### Step 2: Install FFmpeg

1. Create a directory: `C:\ffmpeg`
2. Copy the contents of the `bin` folder from extracted ZIP to `C:\ffmpeg\bin`
3. You should have these files:
   - `C:\ffmpeg\bin\ffmpeg.exe`
   - `C:\ffmpeg\bin\ffprobe.exe`
   - `C:\ffmpeg\bin\ffplay.exe`

### Step 3: Add FFmpeg to System PATH (Recommended)

1. Open Windows Search and type "Environment Variables"
2. Click "Edit the system environment variables"
3. Click "Environment Variables..." button
4. Under "System variables", find and select "Path", then click "Edit"
5. Click "New" and add: `C:\ffmpeg\bin`
6. Click "OK" on all windows
7. **Restart your Command Prompt** (or restart your computer)

### Step 4: Verify Installation

Open Command Prompt and run:
```cmd
ffmpeg -version
```

You should see FFmpeg version information. If you get an error, double-check the PATH configuration.

## Alternative: Manual Configuration

If you don't want to add FFmpeg to PATH, you can specify the full path in `includes/config.php`:

```php
// FFmpeg Configuration
define('FFMPEG_PATH', 'C:\\ffmpeg\\bin\\ffmpeg.exe');
define('FFPROBE_PATH', 'C:\\ffmpeg\\bin\\ffprobe.exe');
```

## Testing the Integration

### Test 1: Record Audio
1. Log in as a student
2. Go to "التلاوة" (Recitation) page
3. Click the microphone icon to record audio
4. Record for 5-10 seconds
5. Stop recording
6. Fill in the form and upload
7. **Expected Result**: File should be converted to `.mp3` format

### Test 2: Record Video
1. On the recitation page, switch to "تسجيل فيديو" (Video Recording) tab
2. Click "بدء التسجيل" (Start Recording)
3. Allow camera/microphone access
4. Record for 5-10 seconds
5. Stop recording and upload
6. **Expected Result**: File should be converted to `.mp4` format

### Test 3: Verify Playback
1. View your uploaded recitation
2. Verify the audio/video plays correctly
3. Check that seeking (jumping to different time positions) works smoothly

## Troubleshooting

### FFmpeg Not Found Error
- Verify FFmpeg is installed at `C:\ffmpeg\bin\ffmpeg.exe`
- Check that you added the correct path to Environment Variables
- Restart Command Prompt/Terminal after adding to PATH
- Restart Apache in XAMPP

### Conversion Fails
- Check PHP error logs in `C:\xampp\apache\logs\error.log`
- Ensure the `uploads` directory has write permissions
- Check that uploaded files aren't too large (default max: 10MB)

### Files Still in WebM Format
- FFmpeg conversion is enabled by default
- Check `includes/config.php` - ensure `ENABLE_FFMPEG_CONVERSION` is `true`
- If FFmpeg is not available, files will be saved as WebM (fallback behavior)

### Permission Issues on Windows
- Run XAMPP as Administrator
- Ensure `uploads` folder has write permissions
- Check Windows Firewall isn't blocking FFmpeg

## Disabling FFmpeg Conversion

If you want to disable automatic conversion and keep WebM files:

Edit `includes/config.php`:
```php
define('ENABLE_FFMPEG_CONVERSION', false);
```

## Performance Notes

- Conversion adds 5-30 seconds processing time depending on file size
- MP3/MP4 files are usually smaller than WebM
- Original WebM files are deleted after successful conversion
- If conversion fails, original WebM file is kept

## Support

For issues or questions, check the error logs or contact the development team.
