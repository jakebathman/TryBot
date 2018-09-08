<?php
namespace App\Http\Controllers;

use App\Http\Controllers\ClassHelper;
use App\Http\Controllers\Slack\Helpers\Attachment;
use App\Http\Controllers\Slack\Helpers\Message;
use App\Http\Controllers\Slack\Slack;
use App\Http\Models\Twitch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Ixudra\Curl\Facades\Curl;
use \App\Exceptions\CurlTimeoutException;

class TwitchController extends ClassHelper
{
    protected $redis;
    protected $baseApiUrlv5 = "https://api.twitch.tv";
    protected $clientId;

    public function __construct()
    {
        $this->redis    = new Redis();
        $this->clientId = env('TWITCH_API_CLIENT_ID', null);
    }

    /**
     * Get info about current streamers, with an optional timeout if time is a
     * factor.
     *
     * @param      int    $timeout  The timeout, in milliseconds (1s = 1000ms)
     *
     * @return     array  The array of streaming info. If no streamers, an empty array is returned.
     */
    public function getStreamers($timeout = 20)
    {
        $url = "https://reddittryhard.com/twitch/twitch.php";

        // Get list of usernames from the database to add to this call
        $twitch    = Twitch::where('is_active', '=', '1')->get();
        $moreUsers = $twitch->pluck('twitch_username')->implode(',');
        $url .= "?" . http_build_query(['u' => $moreUsers]);

        $streamers = Curl::to($url)
            ->withTimeout($timeout)
            ->returnResponseObject()
            ->asJson()
            ->get();

        if (isset($streamers->error)) {
            // Error getting the streams
            throw new CurlTimeoutException($streamers->error, $streamers->status);
        }

        return (array) $streamers->content;
    }

    /**
     * Get the User ID for a given username from the Twitch API
     *
     * @param      string       $username  The username to look up
     *
     * @return     string|null  The User ID returned from Twitch, or null if the user lookup wasn't successful
     */
    public function getUserIdFromUserName($username)
    {
        $url = $this->baseApiUrlv5 . "/kraken/users?" . http_build_query(['login' => $username]);

        $result = Curl::to($url)
            ->withHeader('Accept: application/vnd.twitchtv.v5+json')
            ->withHeader('Client-ID: ' . $this->clientId)
            ->returnResponseObject()
            ->asJson()
            ->get();

        // Parse the result for the user's ID
        $users = new Collection($result->content->users);
        return $users->pluck('_id')->first();
    }

    public function getNewlyStartedStreams()
    {
        $results = [];

        $streamers = collect($this->getStreamers());

        \Log::info("Current streams:" . $streamers->pluck('username')->implode(','));
        \Log::info($streamers);

        $slack = new Slack;

        $sentStreamingAlertPreamble = false;

        // Loop over the streamers and see if they're already set in redis
        foreach ($streamers as $stream) {
            $redisKey                   = 'Twitch:Streaming:' . $stream->username;
            $results[$stream->username] = null;
            $s                          = Redis::get($redisKey);
            if (!$s) {
                // This is a new stream, so notify the channel

                $message = $this->buildTwitchMessage([$stream], false, false, true);

                if ($sentStreamingAlertPreamble === false) {
                    // Send an initial notification that some streams have started
                    $alertMessage = new Message();
                    $alertMessage->setText("Now streaming:");
                    $this->postMessage($alertMessage, $slack->casualChannelId);
                    $sentStreamingAlertPreamble = true;
                }

                \Log::info("Posing to channel");
                $results[$stream->username][] = $this->postMessage($message, $slack->casualChannelId, $stream->username);
                \Log::info("Message");
                \Log::info($message->build());

                // If it's a Destiny stream, also send to the Destiny channel
                if (preg_match('/destiny/i', $stream->game)) {
                    // This is a destiny stream (probably)
                    \Log::info("Posing to Destiny channel");
                    $results[$stream->username][] = $this->postMessage($message, $slack->channels['destiny'], $stream->username);
                }
                // If it's an Overwatch stream, also send to the Overwatch channel
                if (preg_match('/overwatch/i', $stream->game)) {
                    // This is an Overwatch stream (probably)
                    \Log::info("Posing to Overwatch channel");
                    $results[$stream->username][] = $this->postMessage($message, $slack->channels['overwatch'], $stream->username);
                }
                // If it's a Fortnite stream, also send to the Fortnite channel
                if (preg_match('/fortnite/i', $stream->game)) {
                    // This is a Fortnite stream (probably)
                    \Log::info("Posing to Fortnite channel");
                    $results[$stream->username][] = $this->postMessage($message, $slack->channels['fortnite'], $stream->username);
                }
                // If it's a CoD stream, also send to the Call of Duty channel
                if (preg_match('/call of duty/i', $stream->game)) {
                    // This is a CoD stream (probably)
                    \Log::info("Posing to CoD channel");
                    $results[$stream->username][] = $this->postMessage($message, $slack->channels['callofduty'], $stream->username);
                }
            } else {
                // Check that the current game matches the one we sent, and update if it's changed
                // TODO

                // Get the current game from redis
                $currentGame = Redis::get($redisKey);
                $newGame     = $stream->game ?: " ";
                // $newGame     = "Over Watch";
                \Log::info("currentGame: $currentGame");
                \Log::info("newGame: $newGame");
                if ($currentGame != $newGame) {
                    // The game has changed, so let's update the message we sent
                    $messageInfo = Redis::get('Twitch:Streaming:RecentMessages:' . $stream->username);
                    \Log::info("messageInfo: $messageInfo");
                    if ($messageInfo) {
                        // We have record of the message timestamp, which is required to update a message
                        $messageInfo = explode(":", $messageInfo);

                        // Build a message for the stream
                        // $stream->game = $newGame;
                        $message = $this->buildTwitchMessage([$stream], false, false, true);

                        // Update the existing message with the new one
                        \Log::info((array) $this->updateMessage($messageInfo[0], $message, $messageInfo[1], $stream->username));
                    }
                }
            }

            // Set the stream in redis, with a 6 minute expiration baked in, for all streams (which will just extend the time for current streams)
            Redis::setEx($redisKey, 60 * 6, $stream->game ?: " ");
        }
        return compact('streamers', 'results');
    }

    public function postMessage(Message $message, $channelId, $username = null)
    {
        // Send to Slack
        $slack    = new Slack;
        $response = $slack->postMessage($message, $channelId);

        // If there's an attachment in the message, log it so we can update the game
        // within a few minutes (if needed)
        if ($username) {
            $this->logTwitchPostForUpdate($response, $username);
        }

        return $response;
    }

    public function updateMessage($messageTs, Message $message, $channelId, $username = null)
    {
        // Send to Slack
        $slack    = new Slack;
        $response = $slack->updateMessage($messageTs, $message, $channelId);

        // If there's an attachment in the message, log it so we can update the game
        // within a few minutes (if needed)
        if ($username) {
            $this->logTwitchPostForUpdate($response, $username);
        }

        return $response;
    }

    public function logTwitchPostForUpdate($response, $username)
    {
        $response = collect($response->content);
        \Log::info($response->toJson());
        if ($response->get('ok') == 'true' && $response->get('ts') !== null && $response->get('channel') !== null) {
            // See if redis knows about this message, based on its timestamp
            $redisKey = 'Twitch:Streaming:RecentMessages:' . $username;
            $m        = Redis::get($redisKey);
            if (!$m) {
                // Log the message in redis, expiring in 10 minutes
                Redis::setEx($redisKey, 60 * 10, $response->get('ts') . ":" . $response->get('channel'));
            }
        }
    }

    public function buildTwitchMessage($streamers, $includePreamble = false, $includeViewerCount = true, $useLargePreviewImage = false, $includeMultiTwitch = false)
    {
        $message = new Message();
        $message->messageVisibleToChannel();

        $streamers = collect($streamers);

        if ($streamers->isEmpty()) {
            // No one is currently streaming
            $message->setText("Sorry, no one is currently streaming right now. If you just started, give Twitch a few minutes to let me know and try again!");
            $message->messageVisibleToChannel(false);
        } else {
            // Create a multitwitch URL
            $streamCollection = new Collection($streamers);
            $multitwitch      = "multitwitch.tv/" . $streamCollection->implode('username', '/');

            $preamble = null;
            if (count($streamers) === 1) {
                $preamble = "There is 1 person online! ";
            } else {
                $preamble = "There are " . count($streamers) . " people online! ";
            }

            if ($includePreamble) {
                $headers[] = $preamble;
            }

            if ($includeMultiTwitch === true && count($streamers) > 1) {
                $headers[] = $multitwitch;
            }

            if (!empty($headers)) {
                $message->setText(implode("\n\n", $headers));
            }

            foreach ($streamers as $k => $v) {
                $strViewers = "viewer" . ($v->viewers == 1 ? "" : "s");

                // Set the user/gamertag
                $strResponse = "*" . $v->username . "*" . ((strtolower($v->username) != strtolower($v->gamertag)) && (strlen($v->gamertag) > 0) ? " (" . $v->gamertag . ")" : "");

                // Set streaming description and title (if it's set)
                if (empty($v->game)) {
                    $strResponse .= " is now streaming";
                } else {
                    $strResponse .= " is streaming " . "_" . $v->game . "_";
                }

                // Set number of viewers, if it should be included
                if ($includeViewerCount) {
                    if ($v->viewers > 0) {
                        $strResponse .= " to " . $v->viewers . " " . $strViewers;
                    }
                }

                $a = new Attachment();
                $a->setUrl($v->url, $v->status);
                $a->setText($strResponse);
                \Log::info("image");
                \Log::info($v->image);
                if ($useLargePreviewImage) {
                    $a->setImageURL($v->image);
                } else {
                    $a->setThumbURL($v->image);
                }
                $a->processMarkdownForText();

                $message->addAttachment($a->build());
            }
        }
        return $message;
    }
}
