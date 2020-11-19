<?php

namespace ProtoneMedia\LaravelFFMpeg\Tests;

use ProtoneMedia\LaravelFFMpeg\FFMpeg\AttachedPicFormat;
use ProtoneMedia\LaravelFFMpeg\FFMpeg\CopyFormat;
use ProtoneMedia\LaravelFFMpeg\Filesystem\Media;
use ProtoneMedia\LaravelFFMpeg\MediaOpener;

class VideoWithAttachedPicTest extends TestCase
{
    /** @test */
    public function it_can_add_an_image_as_a_attached_pic_to_a_video()
    {
        $this->fakeLocalVideoFile();
        $this->addTestFile('logo.png');

        (new MediaOpener)->open(['video.mp4', 'logo.png'])
            ->export()
            ->addFormatOutputMapping(new CopyFormat, null, ['0'])
            ->addFormatOutputMapping(new AttachedPicFormat, Media::make('local', 'new_video.mp4'), ['1'])
            ->save();

        $streams = (new MediaOpener)->open('new_video.mp4')->getStreams();

        $this->assertCount(3, $streams);
        $this->assertEquals('png', $streams[2]->get('codec_name'));
        $this->assertEquals(1, $streams[2]->get('disposition')['attached_pic']);
    }
}
