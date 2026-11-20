<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class VideoInspector
{
    public function inspect(UploadedFile $file): array
    {
        $process = new Process([config('spacekind.ffprobe_binary', 'ffprobe'), '-v', 'error', '-protocol_whitelist', 'file,pipe', '-show_entries', 'format=duration,format_name:stream=codec_type,codec_name,width,height', '-of', 'json', $file->getRealPath()]);
        $process->setTimeout(10);
        try {
            $process->mustRun();
            $data = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['video' => 'The video could not be verified. Use a valid MP4 (H.264) or WebM file. The server also needs FFprobe installed.']);
        }
        $video = collect($data['streams'] ?? [])->firstWhere('codec_type', 'video');
        $audio = collect($data['streams'] ?? [])->where('codec_type', 'audio');
        $duration = (float) ($data['format']['duration'] ?? 0);
        $ext = strtolower($file->getClientOriginalExtension());
        $format = $data['format']['format_name'] ?? '';
        $compatible = $ext === 'mp4' ? ($video['codec_name'] ?? '') === 'h264' && str_contains($format, 'mp4') && $audio->every(fn ($a) => ($a['codec_name'] ?? '') === 'aac') : in_array($video['codec_name'] ?? '', ['vp8', 'vp9']) && str_contains($format, 'webm') && $audio->every(fn ($a) => in_array($a['codec_name'] ?? '', ['opus', 'vorbis']));
        if (! $video || ! $compatible || $duration <= 0 || $duration > config('spacekind.video_max_seconds', 90) || min($video['width'] ?? 0, $video['height'] ?? 0) < 240 || max($video['width'] ?? 0, $video['height'] ?? 0) > 3840 || count($data['streams'] ?? []) > 3) {
            throw ValidationException::withMessages(['video' => 'Use an MP4 with H.264/AAC or WebM with VP8/VP9 and Opus/Vorbis, 240–3840px, up to 90 seconds.']);
        }

        return ['mime' => $ext === 'mp4' ? 'video/mp4' : 'video/webm', 'duration' => round($duration, 2), 'bytes' => $file->getSize()];
    }
}
