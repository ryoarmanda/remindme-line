<?php

namespace LINE\LINEBot\RemindMe;

use LINE\LINEBot;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Event\FollowEvent;
use LINE\LINEBot\Event\JoinEvent;
use LINE\LINEBot\Event\LeaveEvent;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\PostbackEvent;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\Event\Parser\EventRequestParser;
use LINE\LINEBot\Exception\InvalidEventRequestException;
use LINE\LINEBot\Exception\InvalidSignatureException;
use LINE\LINEBot\RemindMe\EventHandler\FollowEventHandler;
use LINE\LINEBot\RemindMe\EventHandler\JoinEventHandler;
use LINE\LINEBot\RemindMe\EventHandler\PostbackEventHandler;
use LINE\LINEBot\RemindMe\EventHandler\MessageHandler\TextMessageHandler;
use \LINE\LINEBot\SignatureValidator as SignatureValidator;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;

class Route {
	public function register(\Slim\App $app) {

		$app -> get('/', function($req, $res) {
			echo "RemindMe sedang berfungsi!";
		});

		$app -> post('/webhook', function ($req, $res) {
			$bot = $this -> bot;

			$signature = $req -> getHeader(HTTPHeader::LINE_SIGNATURE);
            if (empty($signature)) {
                return $res -> withStatus(400, 'Bad Request');
            }

            try {
                $events = $bot -> parseEventRequest($req -> getBody(), $signature[0]);
            } catch (InvalidSignatureException $e) {
                return $res -> withStatus(400, 'Invalid signature');
            } catch (InvalidEventRequestException $e) {
                return $res -> withStatus(400, "Invalid event request");
            }

			foreach ($events as $event) {
				$handler = null;

				if ($event instanceof MessageEvent) {
					if ($event instanceof TextMessage) {
						$handler = new TextMessageHandler($bot, $event);
					}
				} elseif ($event instanceof FollowEvent) {
					$handler = new FollowEventHandler($bot, $event);
				} elseif ($event instanceof JoinEvent) {
					$handler = new JoinEventHandler($bot, $event);
				} elseif ($event instanceof PostbackEvent) {
					$handler = new PostbackEventHandler($bot, $event);
				}

				$handler -> handle();
			}

			$res -> write('OK');
			return $res;
		});

		$app -> get('/dailyReminder', function($req, $res) {
			extract(parse_url($_ENV['DATABASE_URL']));
			$dbname = substr($path, 1);
			$credentials = "user=$user password=$pass host=$host dbname=$dbname";
			$db = pg_connect($credentials);

			$date = date('Y-m-d', strtotime('+1 day'));

			$result = pg_query_params($db, "SELECT DISTINCT id FROM remindme WHERE deadline=$1", array($date));
			$count = pg_num_rows($result);

			if ($count) {
				while ($id = pg_fetch_row($result)) {
					$query = pg_query_params($db, "SELECT category, task FROM remindme WHERE id=$1 AND deadline=$2", array($id[0], $date));
					$tasks = pg_num_rows($query);

					$details = "Halo!\nHanya mengingatkan saja, kamu memiliki {$tasks} kegiatan besok, yaitu:\n";
					while ($task = pg_fetch_row($query)) $details .= "\n - ($task[0]) $task[1]";
					$reminder = new TextMessageBuilder($details);

					$this -> bot -> pushMessage($id[0], $reminder);
				}
			}
		});

		$app -> get('/clearLastWeek', function($req, $res) {
			extract(parse_url($_ENV['DATABASE_URL']));
			$dbname = substr($path, 1);
			$credentials = "user=$user password=$pass host=$host dbname=$dbname";
			$db = pg_connect($credentials);

			$result = pg_query($db, "DELETE FROM remindme WHERE deadline < current_date - interval '7' day");
		});
	}
}