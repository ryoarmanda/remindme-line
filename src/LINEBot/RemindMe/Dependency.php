<?php

namespace LINE\LINEBot\RemindMe;

use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;

class Dependency {
	public function register(\Slim\App $app) {
		$container = $app -> getContainer();

		$container['bot'] = function($c) {
			$settings = $c -> get('settings');
			$channelToken = $settings['bot']['channelToken'];
			$channelSecret = $settings['bot']['channelSecret'];
			$bot = new LINEBot(new CurlHTTPClient($channelToken), [
				'channelSecret' => $channelSecret
			]);
			return $bot;
		};
	}
}