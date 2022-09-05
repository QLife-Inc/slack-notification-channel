<?php

namespace Illuminate\Tests\Notifications;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Notifications\Channels\SlackWebhookChannel;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class NotificationSlackChannelTest extends TestCase
{
    /**
     * @var \Illuminate\Notifications\Channels\SlackWebhookChannel
     */
    private $slackChannel;

    /**
     * @var \Mockery\MockInterface|\GuzzleHttp\Client
     */
    private $guzzleHttp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guzzleHttp = m::mock(Client::class);

        $this->slackChannel = new SlackWebhookChannel($this->guzzleHttp);
    }

    protected function tearDown(): void
    {
        m::close();
    }

    /**
     * @dataProvider payloadDataProvider
     *
     * @param  \Illuminate\Notifications\Notification  $notification
     * @param  array  $payload
     */
    public function testCorrectPayloadIsSentToSlack(Notification $notification, array $payload)
    {
        $this->guzzleHttp->shouldReceive('post')->andReturnUsing(function ($endpoint, $argPayload) use ($payload) {
            $this->assertEquals($endpoint, 'endpoint');
            $this->assertEquals($argPayload['headers']['Authorization'], 'Bearer token dummy');
            $this->assertEquals($argPayload['headers']['Content-Type'], 'application/json');
            $this->assertEquals($argPayload['json'], $payload);

            return new Response();
        });

        $this->slackChannel->send(new NotificationSlackChannelTestNotifiable, $notification);
    }

    public function payloadDataProvider()
    {
        return [
            'payloadWithIcon' => $this->getPayloadWithIcon(),
            'payloadWithImageIcon' => $this->getPayloadWithImageIcon(),
            'payloadWithoutOptionalFields' => $this->getPayloadWithoutOptionalFields(),
            'payloadWithAttachmentFieldBuilder' => $this->getPayloadWithAttachmentFieldBuilder(),
        ];
    }

    private function getPayloadWithIcon()
    {
        return [
            new NotificationSlackChannelTestNotification,
            [
                'channel' => '#ghost-talk',
                'text' => 'Content',
                'icon_emoji' => ':ghost:',
                'username' => 'Ghostbot',
                'attachments' => [
                    [
                        'title' => 'Laravel',
                        'title_link' => 'https://laravel.com',
                        'text' => 'Attachment Content',
                        'fallback' => 'Attachment Fallback',
                        'fields' => [
                            [
                                'title' => 'Project',
                                'value' => 'Laravel',
                                'short' => true,
                            ],
                        ],
                        'mrkdwn_in' => ['text'],
                        'footer' => 'Laravel',
                        'footer_icon' => 'https://laravel.com/fake.png',
                        'author_name' => 'Author',
                        'author_link' => 'https://laravel.com/fake_author',
                        'author_icon' => 'https://laravel.com/fake_author.png',
                        'ts' => 1234567890,
                    ],
                ],
            ]
        ];
    }

    private function getPayloadWithImageIcon()
    {
        return [
            new NotificationSlackChannelTestNotificationWithImageIcon,
            [
                'username' => 'Ghostbot',
                'icon_url' => 'http://example.com/image.png',
                'channel' => '#ghost-talk',
                'text' => 'Content',
                'attachments' => [
                    [
                        'title' => 'Laravel',
                        'title_link' => 'https://laravel.com',
                        'text' => 'Attachment Content',
                        'fallback' => 'Attachment Fallback',
                        'fields' => [
                            [
                                'title' => 'Project',
                                'value' => 'Laravel',
                                'short' => true,
                            ],
                        ],
                        'mrkdwn_in' => ['text'],
                        'footer' => 'Laravel',
                        'footer_icon' => 'https://laravel.com/fake.png',
                        'ts' => 1234567890,
                    ],
                ],
            ]
        ];
    }

    private function getPayloadWithoutOptionalFields()
    {
        return [
            new NotificationSlackChannelWithoutOptionalFieldsTestNotification,
            [
                'text' => 'Content',
                'channel' => '#ghost-talk',
                'attachments' => [
                    [
                        'title' => 'Laravel',
                        'title_link' => 'https://laravel.com',
                        'text' => 'Attachment Content',
                        'fields' => [
                            [
                                'title' => 'Project',
                                'value' => 'Laravel',
                                'short' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getPayloadWithAttachmentFieldBuilder()
    {
        return [
            new NotificationSlackChannelWithAttachmentFieldBuilderTestNotification,
            [
                'text' => 'Content',
                'channel' => '#ghost-talk',
                'attachments' => [
                    [
                        'title' => 'Laravel',
                        'text' => 'Attachment Content',
                        'title_link' => 'https://laravel.com',
                        'callback_id' => 'attachment_callbackid',
                        'fields' => [
                            [
                                'title' => 'Project',
                                'value' => 'Laravel',
                                'short' => true,
                            ],
                            [
                                'title' => 'Special powers',
                                'value' => 'Zonda',
                                'short' => false,
                            ],
                        ],
                    ],
                ],
            ]
        ];
    }
}

class NotificationSlackChannelTestNotifiable
{
    use Notifiable;

    public function routeNotificationForSlack()
    {
        return [
            'endpoint' => 'endpoint',
            'token' => 'token dummy',
            'channel' => '#ghost-talk'
        ];
    }
}

class NotificationSlackChannelTestNotification extends Notification
{
    public function toSlack($notifiable)
    {
        return (new SlackMessage)
                    ->from('Ghostbot', ':ghost:')
                    ->content('Content')
                    ->attachment(function ($attachment) {
                        $timestamp = m::mock(Carbon::class);
                        $timestamp->shouldReceive('getTimestamp')->andReturn(1234567890);
                        $attachment->title('Laravel', 'https://laravel.com')
                                   ->content('Attachment Content')
                                   ->fallback('Attachment Fallback')
                                   ->fields([
                                       'Project' => 'Laravel',
                                   ])
                                    ->footer('Laravel')
                                    ->footerIcon('https://laravel.com/fake.png')
                                    ->markdown(['text'])
                                    ->author('Author', 'https://laravel.com/fake_author', 'https://laravel.com/fake_author.png')
                                    ->timestamp($timestamp);
                    });
    }
}

class NotificationSlackChannelTestNotificationWithImageIcon extends Notification
{
    public function toSlack($notifiable)
    {
        return (new SlackMessage)
                    ->from('Ghostbot')
                    ->image('http://example.com/image.png')
                    ->content('Content')
                    ->attachment(function ($attachment) {
                        $timestamp = m::mock(Carbon::class);
                        $timestamp->shouldReceive('getTimestamp')->andReturn(1234567890);
                        $attachment->title('Laravel', 'https://laravel.com')
                                   ->content('Attachment Content')
                                   ->fallback('Attachment Fallback')
                                   ->fields([
                                       'Project' => 'Laravel',
                                   ])
                                    ->footer('Laravel')
                                    ->footerIcon('https://laravel.com/fake.png')
                                    ->markdown(['text'])
                                    ->timestamp($timestamp);
                    });
    }
}

class NotificationSlackChannelWithoutOptionalFieldsTestNotification extends Notification
{
    public function toSlack($notifiable)
    {
        return (new SlackMessage)
                    ->content('Content')
                    ->attachment(function ($attachment) {
                        $attachment->title('Laravel', 'https://laravel.com')
                                   ->content('Attachment Content')
                                   ->fields([
                                       'Project' => 'Laravel',
                                   ]);
                    });
    }
}

class NotificationSlackChannelWithAttachmentFieldBuilderTestNotification extends Notification
{
    public function toSlack($notifiable)
    {
        return (new SlackMessage)
            ->content('Content')
            ->attachment(function ($attachment) {
                $attachment->title('Laravel', 'https://laravel.com')
                    ->content('Attachment Content')
                    ->field('Project', 'Laravel')
                    ->callbackId('attachment_callbackid')
                    ->field(function ($attachmentField) {
                        $attachmentField
                            ->title('Special powers')
                            ->content('Zonda')
                            ->long();
                    });
            });
    }
}
