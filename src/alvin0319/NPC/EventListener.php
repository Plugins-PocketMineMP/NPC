<?php
declare(strict_types=1);
namespace alvin0319\NPC;

use alvin0319\NPC\entity\EntityBase;
use alvin0319\NPC\lang\PluginLang;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;

class EventListener implements Listener{

	public function handleReceivePacket(DataPacketReceiveEvent $event){
		$player = $event->getOrigin()->getPlayer();
		$packet = $event->getPacket();

		if($packet instanceof InventoryTransactionPacket){
			if($packet->trData instanceof UseItemOnEntityTransactionData){
				$entity = NPCPlugin::getInstance()->getEntityById($packet->trData->getEntityRuntimeId());

				if(isset(Queue::$removeQueue[$player->getName()])){
					if($entity instanceof EntityBase){
						$player->sendMessage(PluginLang::$prefix . NPCPlugin::getInstance()->getLanguage()->translateLanguage("entity.delete"));
						foreach($entity->getViewers() as $player){
							$entity->despawnTo($player);
						}

						NPCPlugin::getInstance()->removeEntity($entity);
						unset(Queue::$removeQueue[$player->getName()]);
					}else{
						$player->sendMessage(PluginLang::$prefix . NPCPlugin::getInstance()->getLanguage()->translateLanguage("message.notExtend"));
					}
					return;
				}

				if(isset(Queue::$editQueue[$player->getName()])){
					if($entity instanceof EntityBase){
						if(Queue::$editQueue[$player->getName()] ["mode"] === "command"){
							$entity->setCommand(Queue::$editQueue[$player->getName()] ["target"]);
						}elseif(Queue::$editQueue[$player->getName()] ["mode"] === "message"){
							$entity->setMessage(Queue::$editQueue[$player->getName()] ["target"]);
						}else{
							if(is_numeric(Queue::$editQueue[$player->getName()] ["target"])){
								$entity->setScale((float) Queue::$editQueue[$player->getName()] ["target"]);
							}else{
								$player->sendMessage(PluginLang::$prefix . NPCPlugin::getInstance()->getLanguage()->translateLanguage("command.onlyAccept", ["scale", "int"]));
							}
						}
						$player->sendMessage(PluginLang::$prefix . "Edit success.");
						unset(Queue::$editQueue[$player->getName()]);
					}else{
						$player->sendMessage(PluginLang::$prefix . NPCPlugin::getInstance()->getLanguage()->translateLanguage("message.notExtend"));
					}
					return;
				}

				if($entity instanceof EntityBase){
					if(trim($entity->getMessage()) !== ""){
						$player->sendMessage($entity->getMessage());
					}

					if(trim($entity->getCommand()) !== ""){
						$player->getServer()->dispatchCommand($player, $entity->getCommand());
					}
				}
			}
		}
	}

	public function handleQuit(PlayerQuitEvent $event){
		$player = $event->getPlayer();
		foreach(NPCPlugin::getInstance()->getEntities() as $entityBase){
			$entityBase->despawnTo($player);
		}
	}
}