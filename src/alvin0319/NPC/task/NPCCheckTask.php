<?php
declare(strict_types=1);
namespace alvin0319\NPC\task;

use alvin0319\NPC\NPCPlugin;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;

class NPCCheckTask extends Task{

	public function onRun(int $unused) : void{
		foreach(NPCPlugin::getInstance()->getEntities() as $entityBase){
			foreach(NPCPlugin::getInstance()->getServer()->getOnlinePlayers() as $player){
				if($entityBase->getLocation()->getWorld()->getFolderName() === $player->getWorld()->getFolderName()){
					if($entityBase->getLocation()->distance($player->getLocation()) <= (int) NPCPlugin::getInstance()->getConfig()->getNested("spawn-radius", 10)){
						$entityBase->spawnTo($player);
					}else{
						$entityBase->despawnTo($player);
					}
				}else{
					$entityBase->despawnTo($player);
				}
			}
			if(($target = $entityBase->getClosestPlayer()) instanceof Player){
				$entityBase->lookAt($target->getPosition());
			}
		}
	}
}