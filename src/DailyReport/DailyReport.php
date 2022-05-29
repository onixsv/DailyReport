<?php
declare(strict_types=1);

namespace DailyReport;

use BandAPI\BandAPI;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\permission\DefaultPermissions;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;

class DailyReport extends PluginBase implements Listener{

	/** @var Config */
	protected Config $config;

	protected array $db = [];

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->config = new Config($this->getDataFolder() . "Config.yml", Config::YAML, [
			"member_count" => 0,
			"daily_players" => 0,
			"today_player" => 0
		]);
		$this->db = $this->config->getAll();
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			if((int) date("H") === 23 && (int) date("i") === 59){
				$this->post();
				if(BandAPI::getData() === null)
					return;
				$this->db["daily_players"] = 0;
				$this->db["today_player"] = 0;
				$this->db["member_count"] = BandAPI::getData()["member_count"];
			}
		}), 1200);
	}

	protected function onDisable() : void{
		$this->config->setAll($this->db);
		$this->config->save();
	}

	public function post() : void{
		$band = BandAPI::getData();
		if($band === null)
			return;

		$message = "[ 오늘의 오닉스서버 변동 사항 ]\n";
		$new = $band["member_count"] - $this->db["member_count"];
		$new_str = $new > 0 ? "+ {$new}" : "- {$new}";
		//$new = 0;
		//$new_str = $new;
		$message .= "\n밴드 인원: {$band["member_count"]} ({$new_str})\n";
		$message .= "오늘 새로 접속한 인원: " . $this->db["today_player"] . "\n";
		$message .= "오늘 최고 동접: " . $this->db["daily_players"] . "\n";
		$message .= "#오닉스서버 #리포트";

		BandAPI::sendPost($message);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if($sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
			$this->post();
		}

		return true;
	}

	public function onJoin(PlayerJoinEvent $event){
		$player = $event->getPlayer();

		if(!$player->hasPlayedBefore()){
			++$this->db["today_player"];
		}

		if(count($this->getServer()->getOnlinePlayers()) > $this->db["daily_players"]){
			$this->db["daily_players"] = count($this->getServer()->getOnlinePlayers());
		}
	}
}