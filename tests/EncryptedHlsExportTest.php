<?php

namespace ProtoneMedia\LaravelFFMpeg\Tests;

use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Exporters\HLSExporter;
use ProtoneMedia\LaravelFFMpeg\FFMpeg\StdListener;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class EncryptedHlsExportTest extends TestCase
{
    use RetryTrait;

    public static function keyLinePattern()
    {
        return '#EXT-X-KEY:METHOD=AES-128,URI="[~a-zA-Z0-9-_\/:]+.key",IV=[a-z0-9]+';
    }

    /**
     * @test
     * @retry 5
     */
    public function it_can_export_a_single_media_file_into_an_encryped_hls_export()
    {
        $this->fakeLocalVideoFile();

        $lowBitrate = $this->x264()->setKiloBitrate(250);

        FFMpeg::open('video.mp4')
            ->exportForHLS()
            ->withEncryptionKey(HLSExporter::generateEncryptionKey())
            ->addFormat($lowBitrate)
            ->addListener($listener = new StdListener)
            ->save('adaptive.m3u8');

        $this->assertTrue(Storage::disk('local')->has('adaptive.m3u8'));
        $this->assertTrue(Storage::disk('local')->has('adaptive_0_250.m3u8'));

        $playlist = Storage::disk('local')->get('adaptive.m3u8');
        $playlist = preg_replace('/\n|\r\n?/', "\n", $playlist);

        $pattern = '/' . implode("\n", [
            '#EXTM3U',
            HlsExportTest::streamInfoPattern('1920x1080'),
            'adaptive_0_250.m3u8',
            '#EXT-X-ENDLIST',
        ]) . '/';

        $this->assertEquals(1, preg_match($pattern, $playlist), "Playlist mismatch:" . PHP_EOL . $playlist);

        $encryptedPlaylist = Storage::disk('local')->get('adaptive_0_250.m3u8');
        $encryptedPlaylist = preg_replace('/\n|\r\n?/', "\n", $encryptedPlaylist);

        $pattern = '/' . implode("\n", [
            '#EXTM3U',
            '#EXT-X-VERSION:3',
            '#EXT-X-TARGETDURATION:5',
            '#EXT-X-MEDIA-SEQUENCE:0',
            '#EXT-X-PLAYLIST-TYPE:VOD',
            static::keyLinePattern(),
            '#EXTINF:4.720000,',
            'adaptive_0_250_00000.ts',
            '#EXT-X-ENDLIST',
        ]) . '/';

        $this->assertEquals(
            1,
            preg_match($pattern, $encryptedPlaylist),
            "Playlist mismatch:" . PHP_EOL . $encryptedPlaylist . PHP_EOL . PHP_EOL . implode(PHP_EOL, $listener->get()->all())
        );
    }

    /**
     * @test
     * @retry 5
     */
    public function it_can_export_a_single_media_file_into_an_encryped_hls_export_with_rotating_keys()
    {
        $this->fakeLocalVideoFile();

        $lowBitrate = $this->x264()->setKiloBitrate(250);

        $keys     = [];
        $listener = null;

        FFMpeg::open('video.mp4')
            ->exportForHLS()
            ->setKeyFrameInterval(2)
            ->setSegmentLength(2)
            ->addFormat($lowBitrate)
            ->withRotatingEncryptionKey(function ($filename, $contents, $stdListener) use (&$keys, &$listener) {
                $keys[$filename] = $contents;
                $listener = $listener ?: $stdListener;
            })
            ->save('adaptive.m3u8');

        $this->assertCount(4, $keys);

        $this->assertTrue(Storage::disk('local')->has('adaptive.m3u8'));
        $this->assertTrue(Storage::disk('local')->has('adaptive_0_250.m3u8'));

        $encryptedPlaylist = Storage::disk('local')->get('adaptive_0_250.m3u8');
        $encryptedPlaylist = preg_replace('/\n|\r\n?/', "\n", $encryptedPlaylist);

        $pattern = "/" . implode("\n", [
            '#EXTM3U',
            '#EXT-X-VERSION:3',
            '#EXT-X-TARGETDURATION:2',
            '#EXT-X-MEDIA-SEQUENCE:0',
            '#EXT-X-PLAYLIST-TYPE:VOD',
            static::keyLinePattern(),
            '#EXTINF:2.000000,',
            'adaptive_0_250_00000.ts',
            static::keyLinePattern(),
            '#EXTINF:2.000000,',
            'adaptive_0_250_00001.ts',
            static::keyLinePattern(),
            '#EXTINF:0.[0-9]+,',
            'adaptive_0_250_00002.ts',
            '#EXT-X-ENDLIST',
        ]) . "/";

        $this->assertEquals(1, preg_match($pattern, $encryptedPlaylist), "Playlist mismatch:" . PHP_EOL . $encryptedPlaylist . PHP_EOL . PHP_EOL . implode(PHP_EOL, $listener->get()->all()));
    }

    /**
     * @test
     * @retry 5
     */
    public function it_can_set_the_numbers_of_segments_per_key()
    {
        $this->fakeLocalVideoFile();

        $lowBitrate = $this->x264()->setKiloBitrate(250);

        $keys = [];

        FFMpeg::open('video.mp4')
            ->exportForHLS()
            ->setKeyFrameInterval(2)
            ->setSegmentLength(2)
            ->addFormat($lowBitrate)
            ->withRotatingEncryptionKey(function ($filename, $contents) use (&$keys) {
                $keys[$filename] = $contents;
            }, 2)
            ->save('adaptive.m3u8');

        $this->assertCount(2, $keys);

        $this->assertTrue(Storage::disk('local')->has('adaptive.m3u8'));
        $this->assertTrue(Storage::disk('local')->has('adaptive_0_250.m3u8'));

        $encryptedPlaylist = Storage::disk('local')->get('adaptive_0_250.m3u8');
        $encryptedPlaylist = preg_replace('/\n|\r\n?/', "\n", $encryptedPlaylist);

        $pattern = "/" . implode("\n", [
            '#EXTM3U',
            '#EXT-X-VERSION:3',
            '#EXT-X-TARGETDURATION:[0-9]+',
            '#EXT-X-MEDIA-SEQUENCE:0',
            '#EXT-X-PLAYLIST-TYPE:VOD',
            static::keyLinePattern(),
            '#EXTINF:2.[0-9]+,',
            'adaptive_0_250_00000.ts',
            '#EXTINF:2.[0-9]+,',
            'adaptive_0_250_00001.ts',
            static::keyLinePattern(),
            '#EXTINF:0.[0-9]+,',
            'adaptive_0_250_00002.ts',
            '#EXT-X-ENDLIST',
        ]) . "/";

        $this->assertEquals(1, preg_match($pattern, $encryptedPlaylist), "Playlist mismatch:" . PHP_EOL . $encryptedPlaylist);
    }
}
