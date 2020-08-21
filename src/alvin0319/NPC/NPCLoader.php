<?php
declare(strict_types=1);

namespace alvin0319\NPC;

use pocketmine\plugin\PluginBase;

class NPCLoader extends PluginBase{
	/** @var NPCLoader */
	private static $instance;

	public function onLoad() : void{
		self::$instance = $this;
	}

	public function onEnable() : void{
	}

	public function onDisable() : void{
		self::$instance = null;
	}

	private function findGeometryName(array $decodedJsonData) : ?string{
		if(isset($decodedJsonData["geometryName"])){
			return $decodedJsonData["geometryName"];
		}
		if(isset($decodedJsonData["minecraft:geometry"][0]["description"]["identifier"]))
			return $decodedJsonData["minecraft:geometry"][0]["description"]["identifier"];
		return null;
	}
}