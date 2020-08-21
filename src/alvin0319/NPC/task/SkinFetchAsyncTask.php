<?php
declare(strict_types=1);
namespace alvin0319\NPC\task;

use alvin0319\NPC\util\Promise;
use pocketmine\entity\Skin;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class SkinFetchAsyncTask extends AsyncTask{
	/** @var int */
	public const EXTENSION_MISSING = 0;
	/** @var string */
	protected $path;
	/** @var string */
	protected $skinId;
	/** @var string */
	protected $geometryName;
	/** @var string */
	protected $geometryData;

	public function __construct(string $path, string $skinId, string $geometryName, string $geometryData, Promise $promise){
		$this->path = $path;
		$this->skinId = $skinId;
		$this->geometryName = $geometryName;
		$this->geometryData = $geometryData;
		$this->storeLocal($promise);
	}

	public function onRun() : void{
		$img = imagecreatefrompng($this->path);
		$bytes = '';
		for($y = 0; $y < imagesy($img); $y++){
			for($x = 0; $x < imagesx($img); $x++){
				$rgba = @imagecolorat($img, $x, $y);
				$a = ((~((int) ($rgba >> 24))) << 1) & 0xff;
				$r = ($rgba >> 16) & 0xff;
				$g = ($rgba >> 8) & 0xff;
				$b = $rgba & 0xff;
				$bytes .= chr($r) . chr($g) . chr($b) . chr($a);
			}
		}
		@imagedestroy($img);
		$skin = new Skin($this->skinId, $bytes, "", $this->geometryName, $this->geometryData);
		$this->setResult($skin);
	}

	public function onCompletion(Server $server) : void{
		/** @var Promise $promise */
		$promise = $this->fetchLocal();
		$promise->resolve($this->getResult());
	}
}