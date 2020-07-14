<?php

namespace LINE\LINEBot\RemindMe\EventHandler\MessageHandler;

use LINE\LINEBot;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\RemindMe\EventHandler;

use LINE\LINEBot\ImagemapActionBuilder\AreaBuilder;
use LINE\LINEBot\ImagemapActionBuilder\ImagemapMessageActionBuilder;
use LINE\LINEBot\ImagemapActionBuilder\ImagemapUriActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\DatetimePickerTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;
use LINE\LINEBot\MessageBuilder\Imagemap\BaseSizeBuilder;
use LINE\LINEBot\MessageBuilder\ImagemapMessageBuilder;
use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ConfirmTemplateBuilder;

class TextMessageHandler implements EventHandler {
	private $bot;
	private $textMessage;

	public function __construct($bot, $textMessage) {
		$this -> bot = $bot;
		$this -> textMessage = $textMessage;
	}

	public function handle() {
		$msg = trim($this -> textMessage -> getText());
		$id = $this -> textMessage -> getEventSourceId();
		$replyToken = $this -> textMessage -> getReplyToken();

		if ($msg === 'RemindMe') {
			$menu = new TemplateMessageBuilder(
				"Menu RemindMe",
				new ButtonTemplateBuilder(
					'RemindMe: Kegiatan',
					'Apa yang bisa saya bantu?',
					NULL,
					[
						new PostbackTemplateActionBuilder('Tambah kegiatan', 'action=menuAdd'),
						new PostbackTemplateActionBuilder('Cek dengan kategori', 'action=menuCategory'),
						new PostbackTemplateActionBuilder('Cek dengan tanggal', 'action=menuDate'),
						new PostbackTemplateActionBuilder('Kegiatan minggu ini', 'action=menuWeek')
					]
				)
			);
			$this -> bot -> replyMessage($replyToken, $menu);
		} elseif (substr($msg, 0, 4) === '/add') {
			$extra = substr($msg, 5);

			if ($extra === false) {
				$this -> bot -> replyText($replyToken, "=> Format: \"/add [kegiatan]\"\n\nContoh: /add Essay Bahasa Inggris");
			} else {
				$db = pg_connect($this -> parseDatabaseUrl());

				$result = pg_query_params($db, "SELECT category FROM categorylist WHERE id=$1 ORDER BY category", array($id));
				$count = pg_num_rows($result) + 1;

				if ($count) {
					$messageCount = (int)ceil($count / 15);
					if ($messageCount <= 5) {
						$categoryList = new MultiMessageBuilder();
						for ($i = 1; $i <= $messageCount; $i++) {
							$columns = array();
							if ($i < $messageCount) {
								for ($j = 1; $j <= 5; $j++) {
									$buttons = array();
									for ($k = 1; $k <= 3; $k++) {
										if ($i === 1 && $j === 1 && $k === 1) {
											$buttons[] = new PostbackTemplateActionBuilder("[Tambah kategori]", "action=newCategory", "Tambah kategori baru");
										} else {
											$row = pg_fetch_row($result);
											$cat = $row[0];
											$label = $cat;
											if (strlen($label) > 20) $label = substr($label, 0, 17) . "...";
											$buttons[] = new PostbackTemplateActionBuilder("{$label}", "action=setDate&cat={$cat}&task={$extra}", "Masukkan ke kategori {$cat}");
										}
									}
									
									$columns[] = new CarouselColumnTemplateBuilder(
										"RemindMe: Pilih kategori",
										"Kategori yang tersedia:",
										NULL,
										$buttons
									);
								}
							} else {
								$lastMessageColumnCount = (int)ceil(($count % 15) / 3);
								if ($lastMessageColumnCount === 0) $lastMessageColumnCount = 5;

								for ($j = 1; $j <= $lastMessageColumnCount; $j++) {
									$buttons = array();
									if ($j < $lastMessageColumnCount) {
										for ($k = 1; $k <= 3; $k++) {
											if ($i === 1 && $j === 1 && $k === 1) {
												$buttons[] = new PostbackTemplateActionBuilder("[Tambah kategori]", "action=newCategory", "Tambah kategori baru");
											} else {
												$row = pg_fetch_row($result);
												$cat = $row[0];
												$label = $cat;
												if (strlen($label) > 20) $label = substr($label, 0, 17) . "...";
												$buttons[] = new PostbackTemplateActionBuilder("{$label}", "action=setDate&cat={$cat}&task={$extra}", "Masukkan ke kategori {$cat}");
											}
										}
									} else {
										$lastColumnButtonCount = $count % 3;
										if ($lastColumnButtonCount === 0) $lastColumnButtonCount = 3;
										for ($k = 1; $k <= 3; $k++) {
											if ($k <= $lastColumnButtonCount) {
												if ($i === 1 && $j === 1 && $k === 1) {
													$buttons[] = new PostbackTemplateActionBuilder("[Tambah kategori]", "action=newCategory", "Tambah kategori baru");
												} else {
													$row = pg_fetch_row($result);
													$cat = $row[0];
													$label = $cat;
													if (strlen($label) > 20) $label = substr($label, 0, 17) . "...";
													$buttons[] = new PostbackTemplateActionBuilder("{$label}", "action=setDate&cat={$cat}&task={$extra}", "Masukkan ke kategori {$cat}");
												}
											} else {
												$buttons[] = new PostbackTemplateActionBuilder("-", "action=ignore");
											}
										}
									}
									
									$columns[] = new CarouselColumnTemplateBuilder(
										"RemindMe: Pilih kategori",
										"Kategori yang tersedia:",
										NULL,
										$buttons
									);
								}
							}

							$carousel = new CarouselTemplateBuilder($columns);
							$categoryList -> add(new TemplateMessageBuilder("Pilih kategori", $carousel));
						}

						$this -> bot -> replyMessage($replyToken, $categoryList);
					}
				}
			}
		} elseif (substr($msg, 0, 4) === '/new') {
			$extra = substr($msg, 5);

			if ($extra === false) {
				$this -> bot -> replyText($replyToken, "=> Format: \"/new [kategori]\"\n\nContoh: /new tugas");
			} else {
				$db = pg_connect($this -> parseDatabaseUrl());

				$result = pg_query_params($db, "SELECT * FROM categorylist WHERE id=$1 AND category=$2", array($id, strtolower($extra)));
				$count = pg_num_rows($result);

				if ($count) {
					$this -> bot -> replyText($replyToken, "Ups, sepertinya kategori tersebut sudah ada di ruang chat ini.\n\nUntuk kembali ke menu utama, ketik RemindMe");
				} else {
					$result = pg_query_params($db, "INSERT INTO categorylist (id, category) VALUES ($1, $2)", array($id, strtolower($extra)));

					if ($result) {
						$this -> bot -> replyText($replyToken, "Hore! Kategori berhasil disimpan\n\nJika sebelumnya sedang menambahkan kegiatan, mohon diulang lagi melalui menu utama.\nUntuk kembali ke menu utama, ketik RemindMe");
					} else {
						$this -> bot -> replyText($replyToken, 'Hmm sepertinya ada kesalahan. Coba ulangi sekali lagi.');
					}
				}
			}
		}
	}

	private function parseDatabaseUrl() {
		extract(parse_url($_ENV['DATABASE_URL']));
		$dbname = substr($path, 1);
		return "user=$user password=$pass host=$host dbname=$dbname";
	}
}