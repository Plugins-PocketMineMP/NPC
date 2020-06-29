<?php
declare(strict_types=1);
namespace alvin0319\NPC;

use alvin0319\NPC\entity\EntityBase;
use alvin0319\NPC\entity\NPCHuman;
use alvin0319\NPC\lang\PluginLang;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use function is_numeric;

class EventListener implements Listener{

	public function handleReceivePacket(DataPacketReceiveEvent $event){
		$player = $event->getPlayer();
		$packet = $event->getPacket();

		if($packet instanceof InventoryTransactionPacket){
			if($packet->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY){
				$entity = NPCPlugin::getInstance()->getEntityById($packet->trData->entityRuntimeId);

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
								$player->sendMessage(PluginLang::$prefix . NPCPlugin::getInstance()->getLanguage()->translateLanguage("command.onlyAccept", ["scale", "float"]));
							}
						}
						$player->sendMessage(PluginLang::$prefix . "Edit success.");
						unset(Queue::$editQueue[$player->getName()]);
					}else{
						$player->sendMessage(PluginLang::$prefix . NPCPlugin::getInstance()->getLanguage()->translateLanguage("message.notExtend"));
					}
					return;
				}

				if(isset(Queue::$itemQueue[$player->getName()])){
					if($entity instanceof EntityBase){
						if($entity instanceof NPCHuman){
							$entity->setItem(Queue::$itemQueue[$player->getName()]);
							unset(Queue::$itemQueue[$player->getName()]);
							$player->sendMessage(PluginLang::$prefix . "Succeed to set item.");
						}else{
							$player->sendMessage(PluginLang::$prefix . "That entity is not NPCHuman");
						}
					}else{
						$player->sendMessage(PluginLang::$prefix . NPCPlugin::getInstance()->getLanguage()->translateLanguage("message.notExtend"));
					}
					return;
				}

				if(isset(Queue::$offItemQueue[$player->getName()])){
					if($entity instanceof NPCHuman){
						$entity->setItem(Queue::$offItemQueue[$player->getName()], true);
						unset(Queue::$offItemQueue[$player->getName()]);
						$player->sendMessage(PluginLang::$prefix . "Succeed to set offhand item.");
					}else{
						$player->sendMessage(PluginLang::$prefix . "That entity is not NPCHuman");
					}
				}

				if($entity instanceof EntityBase || $entity instanceof NPCHuman){
					$entity->interact($player);
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

	public function handleMove(PlayerMoveEvent $event){
		$player = $event->getPlayer();
		foreach(NPCPlugin::getInstance()->getEntities() as $entityBase){
			if($entityBase->getLocation()->getLevel()->getFolderName() === $player->getLevel()->getFolderName()){
				if($entityBase->getLocation()->distance($player->getLocation()) <= (int) NPCPlugin::getInstance()->getConfig()->getNested("spawn-radius", 10)){
					$entityBase->spawnTo($player);
					$entityBase->lookAt($player);
				}else{
					$entityBase->despawnTo($player);
				}
			}else{
				$entityBase->despawnTo($player);
			}
		}
	}
}