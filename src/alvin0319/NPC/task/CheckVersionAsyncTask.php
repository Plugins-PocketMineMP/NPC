<?php
declare(strict_types=1);

namespace alvin0319\NPC\task;

use alvin0319\NPC\util\Promise;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;

use function is_bool;
use function json_decode;

class CheckVersionAsyncTask extends AsyncTask{

	public function __construct(Promise $promise){
		$this->storeLocal($promise);
	}

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
		/** @var Promise $promise */
		$promise = $this->fetchLocal();
		if($this->getResult() === null){
			$promise->reject("Update check failed: Failed to connect Github server.");
		}else{
			$promise->resolve($this->getResult());
		}
	}
}