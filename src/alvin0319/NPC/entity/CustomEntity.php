<?php
declare(strict_types=1);

namespace alvin0319\NPC\entity;

use pocketmine\entity\Living;

use function spl_object_id;

class CustomEntity extends Living{
	use EntityTrait;

	/** @var string */
	protected $networkId;

	public function getName() : string{
		return "CustomEntity #" . spl_object_id($this);
	}
}