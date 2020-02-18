<?php
declare(strict_types=1);
namespace alvin0319\NPC\task;

use alvin0319\NPC\NPCPlugin;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;

class CheckVersionAsyncTask extends AsyncTask{

	public function onRun(){
		$url = Internet::getURL("https://raw.githubusercontent.com/alvin0319/NPC/stable/updates.json");

		if(is_bool($url)){
			$this->setResult(null);
		}else{
			$data = json_decode($url, true);

			if($data !== null){
				$lastVersion = $data["version"];
				$lastMessage = $data["updates"] [$lastVersion] ["message"];
				$mustUpdate = $data["updates"] [$lastVersion] ["mustUpdate"];

				$this->setResult(["version" => $lastVersion, "message" => $lastMessage, "update" => $mustUpdate]);
			}else{
				$this->setResult(null);
			}
		}
	}

	public function onCompletion(Server $server){
		if($this->getResult() === null){
			$plugin = $server->getPluginManager()->getPlugin("NPC");

			if($plugin instanceof NPCPlugin){
				$plugin->getLogger()->error("Update check failed: Failed to connect Github server.");
			}
		}else{
			$plugin = $server->getPluginManager()->getPlugin("NPC");

			if($plugin instanceof NPCPlugin){
				$ver = $plugin->getDescription()->getVersion();

				$lastVer = $this->getResult()["version"];

				if(version_compare($lastVer, $ver) > 0){
					$mustUpdate = $this->getResult()["update"] ?? false;
					$message = $this->getResult()["message"] ?? "";

					$plugin->getLogger()->notice("The New version of NPC was released. Now version: " . $ver . ", Last version: " . $lastVer);
					$plugin->getLogger()->info("Update message: " . $message);

					if($mustUpdate){
						$plugin->getLogger()->emergency("You must update this plugin before you can use it.");
						$server->getPluginManager()->disablePlugin($plugin);
					}
				}else{
					$plugin->getLogger()->info("The latest version.");
				}
			}
		}
	}
}