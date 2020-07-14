<?php

namespace LINE\LINEBot\RemindMe\EventHandler;

use LINE\LINEBot;
use LINE\LINEBot\Event\FollowEvent;
use LINE\LINEBot\RemindMe\EventHandler;

class FollowEventHandler implements EventHandler {
	private $bot;
	private $followEvent;

	public function __construct($bot, FollowEvent $followEvent) {
		$this -> bot = $bot;
		$this -> followEvent = $followEvent;
	}

	public function handle() {
		$this -> bot -> replyText($this -> followEvent -> getReplyToken(),
			"Terima kasih telah menambahkan saya sebagai teman!\n\nRemindMe adalah sebuah bot yang dapat digunakan sebagai pengingat Anda sehari-hari. Anda dapat menambahkan kegiatan yang perlu diingat oleh saya, ataupun menanyakan apa saja yang tersimpan dalam kategori atau waktu tertentu.\n\nBaru pertama kali? Ketik RemindMe dan saya akan memandu Anda."
		);
	}
}