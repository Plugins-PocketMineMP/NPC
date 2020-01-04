<?php
declare(strict_types=1);
namespace alvin0319\NPC;

use alvin0319\NPC\config\EntityConfig;
use alvin0319\NPC\entity\CustomEntity;
use alvin0319\NPC\entity\EntityBase;
use alvin0319\NPC\entity\NPCHuman;
use alvin0319\NPC\lang\PluginLang;
use alvin0319\NPC\task\NPCCheckTask;
use alvin0319\NPC\util\ExtensionNotLoadedException;
use alvin0319\NPC\util\FileNotFoundException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

class NPCPlugin extends PluginBase{

	private static $instance = null;

	protected $data;

	/** @var PluginLang */
	protected $lang;

	protected $entityNames = [
		"npc" => NPCHuman::class
	];

	/** @var EntityBase[] */
	protected $entities = [];

	public function onLoad(){
		self::$instance = $this;
	}

	public static function getInstance() : NPCPlugin{
		return self::$instance;
	}

	public function onEnable(){
		$this->saveResource("config.yml");
		if(!file_exists($this->getDataFolder() . "images/") or !is_dir($this->getDataFolder() . "images/")){
			mkdir($this->getDataFolder() . "images/");
		}

		$this->lang = new PluginLang($this);

		$this->getLogger()->info($this->lang->translateLanguage("plugin.loaded"));

		$this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);

		$this->data = $this->getConfig()->getAll();

		if(!file_exists($file = $this->getDataFolder() . "npc.dat")){
			file_put_contents($file, (new LittleEndianNbtSerializer())->writeCompressed(new TreeRoot(CompoundTag::create()->setTag("npc", new ListTag()))));
		}

		$data = (new LittleEndianNbtSerializer())->readCompressed(file_get_contents($file))->mustGetCompoundTag();

		foreach($data->getListTag("npc")->getValue() as $tag){
			if($tag instanceof CompoundTag){
				switch($tag->getInt("type")){
					case NPCHuman::NETWORK_ID:
						$class = NPCHuman::nbtDeserialize($tag);
						break;
					case CustomEntity::NETWORK_ID:
						$class = CustomEntity::nbtDeserialize($tag);
						break;
					default:
						throw new \InvalidStateException("Unknown entity type " . $tag->getInt("type"));
				}

				$this->entities[$tag->getString("pos")] = $class;
			}
		}

		$this->getScheduler()->scheduleRepeatingTask(new NPCCheckTask(), 10);
	}

	public function onDisable(){
		$nbt = CompoundTag::create();
		$tag = new ListTag();

		foreach(array_values($this->entities) as $baseEntity){
			$tag->push($baseEntity->nbtSerialize());
		}
		$nbt->setTag("npc", $tag);
		file_put_contents($this->getDataFolder() . "npc.dat", (new LittleEndianNbtSerializer())->writeCompressed(new TreeRoot($nbt)));
		$this->getLogger()->info($this->lang->translateLanguage("plugin.disabled"));
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(!$sender instanceof Player){
			return false;
		}
		switch($args[0] ?? "x"){
			case "create":
				if(isset($args[1])){// type
					if(isset($args[2])){// nametag
						if($args[1] === "npc"){
							if(isset($args[3])){// path, or something
								$path = $args[3];
								$bool = true;
								if(isset($args[4])){// geometry path, or something
									if(file_exists($this->getDataFolder() . "images/" . $args[4])){
										$data = json_decode(file_get_contents($this->getDataFolder() . "images/" . $args[4]), true);

										if(!isset($data["geometryName"])){
											$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("entity.geometry"));
											break;
										}

										$geometryName = $data["geometryName"];
										$geometryData = json_encode($data);
									}else{
										$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("path.notExist", [$args[4]]));
										break;
									}
								}
								try{
									$skin = $this->imageToSkin($sender, $path, isset($geometryName) ? $geometryName : "", isset($geometryData) ? $geometryData : "");
								}catch(ExtensionNotLoadedException $e){
									$bool = false;
									$sender->sendMessage(PluginLang::$prefix . $e->getMessage());
								}catch(FileNotFoundException $e){
									$bool = false;
									$sender->sendMessage(PluginLang::$prefix . $e->getMessage());
								}

								$nbt = CompoundTag::create();

								if($bool && isset($skin)){
									$nbt->setString("name", $args[2]);
									$nbt->setByte("isCustomSkin", 1);
									$nbt->setTag("Skin", $this->getSkinCompound($skin));

									/** @var NPCHuman $entity */
									$entity = new NPCHuman($sender->getLocation(), $nbt);
									$this->entities[self::pos2hash($sender->getLocation())] = $entity;

									$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("entity.spawn", [$entity->getRealName()]));
								}
							}else{
								$skin = $sender->getSkin();
								$nbt = CompoundTag::create();
								$nbt->setString("name", $args[2]);
								$nbt->setTag("Skin", $this->getSkinCompound($skin));

								/** @var NPCHuman $entity */
								$entity = new NPCHuman($sender->getLocation(), $nbt);
								$this->entities[self::pos2hash($sender->getLocation())] = $entity;

								$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("entity.spawn", [$entity->getRealName()]));
							}
						}else{
							if(in_array($args[1], array_keys(EntityConfig::NETWORK_IDS))){
								$nbt = EntityFactory::createBaseNBT($sender->getPosition()->asVector3(), null, $sender->getLocation()->getYaw(), $sender->getLocation()->getPitch());
								$nbt->setString("name", $args[2]);
								/** @var EntityBase $entity */
								$entity = new CustomEntity(EntityConfig::NETWORK_IDS[$args[1]], $sender->getLocation(), $nbt);
								$this->entities[self::pos2hash($sender->getLocation())] = $entity;
								$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("entity.spawn", [$entity->getRealName()]));
							}else{
								$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("entity.notExist"));
							}
						}
					}else{
						$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("command.create.usage"));
					}
				}else{
					$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("command.create.usage"));
				}
				break;
			case "remove":
				Queue::$removeQueue[$sender->getName()] = time();
				$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("command.remove.message"));
				break;
			case "get":
				if(count($this->entities) === 0){
					$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("command.list.empty"));
					break;
				}

				$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("command.list") . implode(", ", array_map(function(EntityBase $entityBase) : string{
					return "#" . $entityBase->getId() . " " . $entityBase->getRealName() . ": " . self::pos2hash($entityBase->getLocation());
				}, array_values($this->entities))));
				break;
			case "edit":
				if(isset($args[1])){
					if(isset($args[2])){
						if(in_array($args[1], ["command", "message"])){
							Queue::$editQueue[$sender->getName()] = [
								"mode" => $args[1],
								"target" => $args[2]
							];
							$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("command.edit.message"));
						}else{
							$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("command.edit.usage"));
						}
					}else{
						$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("command.edit.usage"));
					}
				}else{
					$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("command.edit.usage"));
				}
				break;
			case "message":
				break;
			default:
				$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("command.create.usage"));
				$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("command.delete.usage"));
				$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("command.edit.usage"));
				$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("command.get.usage"));
				$sender->sendMessage(PluginLang::$prefix . $this->lang->translateLanguage("command.entity.list") . implode(", ", array_keys(EntityConfig::NETWORK_IDS)) . ", npc");
		}
		return true;
	}

	/**
	 * @param Player $player
	 * @param string $path
	 * @param string $geometryName
	 * @param string $geometryData
	 * @return Skin
	 * @throws ExtensionNotLoadedException
	 * @throws FileNotFoundException
	 */
	private function imageToSkin(Player $player, string $path, string $geometryName = "", string $geometryData = "") : Skin{
		if(!extension_loaded("gd")){
			throw new ExtensionNotLoadedException($this->lang->translateLanguage("ext-gd.missing"));
		}

		if(!file_exists($path = $this->getDataFolder() . "images/" . $path)){
			throw new FileNotFoundException($this->lang->translateLanguage("path.notExist", [$path]));
		}

		$img = imagecreatefrompng($path);
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
		return new Skin($player->getSkin()->getSkinId(), $bytes, "", $geometryName, $geometryData);
	}

	/**
	 * @param Skin $skin
	 * @return CompoundTag
	 */
	public function getSkinCompound(Skin $skin) : CompoundTag{
		$nbt = CompoundTag::create();
		$nbt->setString("Name", $skin->getSkinId());
		$nbt->setByteArray("Data", $skin->getSkinData());
		$nbt->setByteArray("CapeData", $skin->getCapeData());
		$nbt->setString("GeometryName", $skin->getGeometryName());
		$nbt->setByteArray("GeometryData", $skin->getGeometryData());

		return $nbt;
	}

	/**
	 * @param Location $pos
	 * @return string
	 */
	public static function pos2hash(Location $pos) : string{
		return implode(":", [$pos->x, $pos->y, $pos->z, $pos->world->getFolderName()]);
	}

	/**
	 * @param int $id
	 * @return EntityBase|null
	 */
	public function getEntityById(int $id) : ?EntityBase{
		foreach(array_values($this->entities) as $entityBase){
			if($entityBase->getId() === $id){
				return $entityBase;
			}
		}
		return null;
	}

	/**
	 * @return PluginLang
	 */
	public function getLanguage() : PluginLang{
		return $this->lang;
	}

	/**
	 * @param EntityBase $entityBase
	 */
	public function addEntity(EntityBase $entityBase){
		$this->entities[self::pos2hash($entityBase->getLocation())] = $entityBase;
	}

	/**
	 * @param EntityBase $entityBase
	 */
	public function removeEntity(EntityBase $entityBase){
		unset($this->entities[self::pos2hash($entityBase->getLocation())]);
	}

	/**
	 * @return EntityBase[]
	 */
	public function getEntities() : array{
		return array_values($this->entities);
	}
}