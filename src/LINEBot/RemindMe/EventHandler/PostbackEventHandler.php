<?php

namespace LINE\LINEBot\RemindMe\EventHandler;

use LINE\LINEBot;
use LINE\LINEBot\Event\PostbackEvent;
use LINE\LINEBot\RemindMe\EventHandler;

use LINE\LINEBot\TemplateActionBuilder\DatetimePickerTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;
use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ConfirmTemplateBuilder;

class PostbackEventHandler implements EventHandler {
	private $bot;
	private $postbackEvent;

	public function __construct($bot, PostbackEvent $postbackEvent) {
		$this -> bot = $bot;
		$this -> postbackEvent = $postbackEvent;
	}

	public function handle() {
		$data = $this -> postbackEvent -> getPostbackData();
		$params = $this -> postbackEvent -> getPostbackParams();
		$id = $this -> postbackEvent -> getEventSourceId();
		$replyToken = $this -> postbackEvent -> getReplyToken();

		parse_str($data, $args);

		if ($args['action'] === 'menuAdd') {
			$this -> bot -> replyText($replyToken, "Untuk menambahkan kegiatan, ketik \"/add [kegiatan]\"\n\nContoh: /add Matematika: Integral");
		} elseif ($args['action'] === 'newCategory') {
			$this -> bot -> replyText($replyToken, "Untuk menambahkan kategori, ketik \"/new [kategori]\"\n\nContoh: /new tugas");
		} elseif ($args['action'] === 'setDate') {
			$setdate = new TemplateMessageBuilder(
				"Atur tanggal kegiatan",
				new ButtonTemplateBuilder(
					'RemindMe: Atur Tanggal',
					'Pada tanggal berapa kegiatan tersebut?',
					NULL,
					[
						new DatetimePickerTemplateActionBuilder('Pilih tanggal', "action=add&cat={$args['cat']}&task={$args['task']}", 'date')
					]
				)
			);

			$this -> bot -> replyMessage($replyToken, $setdate);
		} elseif ($args['action'] === 'add') {
			$date = date("d-m-Y", strtotime($params['date']));
			$review = new TextMessageBuilder("Informasi kegiatan\n\nKategori:\n{$args['cat']}\n\nIsi:\n{$args['task']}\n\nTanggal:\n{$date}\n\nJika terdapat kesalahan, mohon ulangi kembali dengan data yang benar.");
			$confirm = new TemplateMessageBuilder(
				"Konfirmasi informasi kegiatan",
				new ConfirmTemplateBuilder(
					'Apakah informasi kegiatan sudah benar?',
					[
						new PostbackTemplateActionBuilder('Ya', "action=yes&cat={$args['cat']}&task={$args['task']}&date={$params['date']}", 'Ya'),
						new PostbackTemplateActionBuilder('Tidak', "action=no", 'Tidak')
					]
				)
			);

			$reply = new MultiMessageBuilder();
			$reply -> add($review)
			       -> add($confirm);

			$this -> bot -> replyMessage($replyToken, $reply);
		} elseif ($args['action'] === 'yes') {
			$db = pg_connect($this -> parseDatabaseUrl());

			$result = pg_query_params($db, "SELECT * FROM remindme WHERE id=$1 AND category=$2 AND task=$3 AND deadline=$4", array($id, strtolower($args['cat']), $args['task'], $args['date']));
			$count = pg_num_rows($result);

			if ($count) {
				$this -> bot -> replyText($replyToken, "Ups, sepertinya kegiatan tersebut sudah ada di ruang chat ini.\n\nUntuk kembali ke menu utama, ketik RemindMe");
			} else {
				$result = pg_query_params($db, "INSERT INTO remindme (id, category, task, deadline) VALUES ($1, $2, $3, $4)", array($id, strtolower($args['cat']), $args['task'], $args['date']));

				if ($result) {
					$this -> bot -> replyText($replyToken, "Penambahan berhasil!\n\nUntuk kembali ke menu utama, ketik RemindMe");
				} else {
					$this -> bot -> replyText($replyToken, 'Hmm sepertinya ada kesalahan. Coba ulangi sekali lagi.');
				}
			}
		} elseif ($args['action'] === 'no') {
			$this -> bot -> replyText($replyToken, "Oke, penambahan dibatalkan\nUntuk kembali ke menu utama, ketik RemindMe");
		} elseif ($args['action'] === 'menuCategory') {
			$db = pg_connect($this -> parseDatabaseUrl());

			$result = pg_query_params($db, "SELECT category FROM categorylist WHERE id=$1 ORDER BY category", array($id));
			$count = pg_num_rows($result);

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
									$row = pg_fetch_row($result);
									$cat = $row[0];
									$label = $cat;
									if (strlen($label) > 20) $label = substr($label, 0, 17) . "...";
									$buttons[] = new PostbackTemplateActionBuilder("{$label}", "action=askCategory&cat={$cat}", "Lihat kategori {$cat}");
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
										$row = pg_fetch_row($result);
										$cat = $row[0];
										$label = $cat;
										if (strlen($label) > 20) $label = substr($label, 0, 17) . "...";
										$buttons[] = new PostbackTemplateActionBuilder("{$label}", "action=askCategory&cat={$cat}", "Lihat kategori {$cat}");
									}
								} else {
									$lastColumnButtonCount = $count % 3;
									if ($lastColumnButtonCount === 0) $lastColumnButtonCount = 3;
									for ($k = 1; $k <= 3; $k++) {
										if ($k <= $lastColumnButtonCount) {
											$row = pg_fetch_row($result);
											$cat = $row[0];
											$label = $cat;
											if (strlen($label) > 20) $label = substr($label, 0, 17) . "...";
											$buttons[] = new PostbackTemplateActionBuilder("{$label}", "action=askCategory&cat={$cat}", "Lihat kategori {$cat}");
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
				} else {

				}
			} else {
				$this -> bot -> replyText($replyToken, "Yah, tidak ada kategori yang tersimpan di ruang chat ini");
			}
		} elseif ($args['action'] === 'askCategory') {
			$db = pg_connect($this -> parseDatabaseUrl());

			$result = pg_query_params($db, "SELECT task, deadline FROM remindme WHERE id=$1 AND category=$2", array($id, $args['cat']));
			$count = pg_num_rows($result);

			if ($count) {
				$details = "Ada {$count} kegiatan dengan kategori {$args['cat']}:\n";
				while ($row = pg_fetch_row($result)) {
					$task = $row[0];
					$date = date('d-m-Y', strtotime($row[1]));
					$details .= "\n - {$task} ({$date})";
				}
				$this -> bot -> replyText($replyToken, $details);
			} else {
				$this -> bot -> replyText($replyToken, "Tidak ada kegiatan dengan kategori {$args['cat']}");
			}
		} elseif ($args['action'] === 'menuDate') {
			$setdate = new TemplateMessageBuilder(
				"Cek kegiatan dengan tanggal",
				new ButtonTemplateBuilder(
					'RemindMe: Cek kegiatan dengan tanggal',
					'Tanggal berapa yang ingin Anda cek?',
					NULL,
					[
						new DatetimePickerTemplateActionBuilder('Pilih tanggal', "action=askDate", 'date')
					]
				)
			);

			$this -> bot -> replyMessage($replyToken, $setdate);
		} elseif ($args['action'] === 'askDate') {
			$db = pg_connect($this -> parseDatabaseUrl());

			$result = pg_query_params($db, "SELECT category, task FROM remindme WHERE id=$1 AND deadline=$2", array($id, $params['date']));
			$count = pg_num_rows($result);

			$date = date('d-m-Y', strtotime($params['date']));
			if ($count) {
				$details = "Ada $count kegiatan pada tanggal $date:\n";
				while ($row = pg_fetch_row($result)) $details .= "\n - ($row[0]) $row[1]";

				$this -> bot -> replyText($replyToken, $details);
			} else {
				$this -> bot -> replyText($replyToken, "Tidak ada kegiatan pada tanggal $date");
			}
		} elseif ($args['action'] === 'menuWeek') {
			$db = pg_connect($this -> parseDatabaseUrl());

			$date = date('d-m-Y', strtotime('-' . date('w') . ' days'));
			$columns = array();
			for ($i = 1; $i <= 5; $i++) {
				$date = date('d-m-Y', strtotime($date . ' +1 day'));
				$qDate = date('Y-m-d', strtotime($date));
				$result = pg_query_params($db, "SELECT task FROM remindme WHERE id=$1 AND deadline=$2", array($id, $qDate));
				$count = pg_num_rows($result);
				$note = "";
				if ($count) {
					$note = "Ada $count kegiatan di hari ini";
				} else {
					$note = "Tidak ada kegiatan di hari ini";
				}

				$columns[] = new CarouselColumnTemplateBuilder(
					"$date",
					"$note",
					NULL,
					[
						new PostbackTemplateActionBuilder('Lihat kegiatan', "action=detailWeek&date={$date}", "Lihat kegiatan pada tanggal $date")
					]
				);
			}

			$weekA = new TemplateMessageBuilder("Kegiatan minggu ini", new CarouselTemplateBuilder($columns));

			$columns = array();
			for ($i = 6; $i <= 7; $i++) {
				$date = date('d-m-Y', strtotime($date . ' +1 day'));
				$qDate = date('Y-m-d', strtotime($date));
				$result = pg_query_params($db, "SELECT task FROM remindme WHERE id=$1 AND deadline=$2", array($id, $qDate));
				$count = pg_num_rows($result);
				$note = "";
				if ($count) {
					$note = "Ada $count kegiatan di hari ini";
				} else {
					$note = "Tidak ada kegiatan di hari ini";
				}

				$columns[] = new CarouselColumnTemplateBuilder(
					"$date",
					"$note",
					NULL,
					[
						new PostbackTemplateActionBuilder('Lihat kegiatan', "action=detailWeek&date={$date}", "Lihat kegiatan pada tanggal $date")
					]
				);
			}

			$weekB = new TemplateMessageBuilder("Kegiatan minggu ini", new CarouselTemplateBuilder($columns));
			
			$schedule = new MultiMessageBuilder();
			$schedule -> add($weekA)
					  -> add($weekB);

			$this -> bot -> replyMessage($replyToken, $schedule);
		} elseif ($args['action'] === 'detailWeek') {
			$db = pg_connect($this -> parseDatabaseUrl());

			$qDate = date('Y-m-d', strtotime($args['date']));
			$result = pg_query_params($db, "SELECT category, task FROM remindme WHERE id=$1 AND deadline=$2", array($id, $qDate));
			$count = pg_num_rows($result);

			if ($count) {
				$details = "Ada $count kegiatan pada tanggal {$args['date']}:\n";
				while ($row = pg_fetch_row($result)) $details .= "\n - ($row[0]) $row[1]";

				$this -> bot -> replyText($replyToken, $details);
			} else {
				$this -> bot -> replyText($replyToken, "Tidak ada kegiatan pada tanggal {$args['date']}");
			}
		}
	}

	private function parseDatabaseUrl() {
		extract(parse_url($_ENV['DATABASE_URL']));
		$dbname = substr($path, 1);
		return "user=$user password=$pass host=$host dbname=$dbname";
	}
}