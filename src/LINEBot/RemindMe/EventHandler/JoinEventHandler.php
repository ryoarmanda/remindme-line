<?php

namespace LINE\LINEBot\RemindMe\EventHandler;

use LINE\LINEBot;
use LINE\LINEBot\Event\JoinEvent;
use LINE\LINEBot\RemindMe\EventHandler;

class JoinEventHandler implements EventHandler {
	private $bot;
	private $joinEvent;

	public function __construct($bot, JoinEvent $joinEvent) {
		$this -> bot = $bot;
		$this -> joinEvent = $joinEvent;
	}

	public function handle() {
		$replyToken = $this -> joinEvent -> getReplyToken();
		if ($this -> joinEvent -> isGroupEvent()) {
			$this -> bot -> replyText($replyToken, 
				"Terima kasih telah menambahkan saya ke dalam grup ini!\n\nRemindMe adalah sebuah bot yang dapat digunakan sebagai pengingat Anda sehari-hari. Anda dapat menambahkan kegiatan yang perlu diingat oleh saya, ataupun menanyakan apa saja yang tersimpan dalam kategori atau waktu tertentu.\n\nBaru pertama kali? Ketik RemindMe dan saya akan memandu Anda."
			);
		} elseif ($this -> joinEvent -> isRoomEvent()) {
			$this -> bot -> replyText($replyToken, 
				"Terima kasih telah menambahkan saya ke dalam room ini!\n\nRemindMe adalah sebuah bot yang dapat digunakan sebagai pengingat Anda sehari-hari. Anda dapat menambahkan kegiatan yang perlu diingat oleh saya, ataupun menanyakan apa saja yang tersimpan dalam kategori atau waktu tertentu.\n\nBaru pertama kali? Ketik RemindMe dan saya akan memandu Anda."
			);
		}
	}
}