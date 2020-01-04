<?php
declare(strict_types=1);
namespace NPC\inventory;

use NPC\entity\EntityBase;
use pocketmine\inventory\BaseInventory;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\player\Player;

class HumanInventory extends BaseInventory{

	/** @var EntityBase */
	protected $holder;

	/** @var int */
	protected $itemInHandIndex = 0;

	/**
	 * @param EntityBase $holder
	 */
	public function __construct(EntityBase $holder){
		$this->holder = $holder;
		parent::__construct(36);
	}

	public function isHotbarSlot(int $slot) : bool{
		return $slot >= 0 and $slot <= $this->getHotbarSize();
	}

	private function throwIfNotHotbarSlot(int $slot) : void{
		if(!$this->isHotbarSlot($slot)){
			throw new \InvalidArgumentException("$slot is not a valid hotbar slot index (expected 0 - " . ($this->getHotbarSize() - 1) . ")");
		}
	}

	public function getHotbarSlotItem(int $hotbarSlot) : Item{
		$this->throwIfNotHotbarSlot($hotbarSlot);
		return $this->getItem($hotbarSlot);
	}

	public function getHeldItemIndex() : int{
		return $this->itemInHandIndex;
	}

	public function setHeldItemIndex(int $hotbarSlot, bool $send = true) : void{
		$this->throwIfNotHotbarSlot($hotbarSlot);

		$this->itemInHandIndex = $hotbarSlot;

		if($this->holder instanceof Player and $send){
			$this->holder->getNetworkSession()->getInvManager()->syncSelectedHotbarSlot();
		}
		foreach($this->holder->getViewers() as $viewer){
			$viewer->getNetworkSession()->sendDataPacket(MobEquipmentPacket::create($this->holder->getId(), $this->getItemInHand(), $this->getHeldItemIndex(), ContainerIds::INVENTORY));;
		}
	}

	public function getItemInHand() : Item{
		return $this->getHotbarSlotItem($this->itemInHandIndex);
	}

	public function setItemInHand(Item $item) : void{
		$this->setItem($this->getHeldItemIndex(), $item);
		foreach($this->holder->getViewers() as $viewer){
			//$viewer->getNetworkSession()->onMobEquipmentChange($this->holder);
			$viewer->sendDataPacket(MobEquipmentPacket::create($this->holder->getId(), $this->getItemInHand(), $this->getHeldItemIndex(), ContainerIds::INVENTORY));
		}
	}

	public function getHotbarSize() : int{
		return 9;
	}

	public function getHolder() : EntityBase{
		return $this->holder;
	}
}