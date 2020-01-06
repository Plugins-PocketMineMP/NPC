<?php
declare(strict_types=1);
namespace NPC\ui;

use pocketmine\form\Form as PMForm;
use pocketmine\Player;

abstract class Form implements PMForm{

	/** @var Form[] */
	protected $player = [];

	public function sendTo(Player $player){
		$this->player[$player->getName()] = $this;
		$player->sendForm($this);
	}

	public function handleResponse(Player $player, $data) : void{
		unset($this->player[$player->getName()]);
	}

	public function getFormByPlayer(Player $player) : ?Form{
		return $this->player[$player->getName()] ?? null;
	}
}