<?php

namespace LINE\LINEBot\RemindMe;

class Setting {
	public static function getSetting() {
		return [
			'settings' => [
				'displayErrorDetails' => true,
				
				'bot' => [
					'channelToken' => getenv('LINEBOT_CHANNEL_TOKEN'),
					'channelSecret' => getenv('LINEBOT_CHANNEL_SECRET')
				]
			]
		];
	}
}