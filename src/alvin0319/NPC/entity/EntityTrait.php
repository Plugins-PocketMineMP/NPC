<?php
declare(strict_types=1);

namespace alvin0319\NPC\entity;

use pocketmine\Player;

trait EntityTrait{

	public function sendSpawnPacket(Player $player) : void{
		if($this instanceof Human){
		}else{
		}
	}
}