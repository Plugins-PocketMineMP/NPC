<?php
declare(strict_types=1);
namespace NPC\lang;

use NPC\NPCPlugin;
use NPC\util\InvalidLanguageException;

class PluginLang{

	protected $data;

	protected $lang;

	protected $plugin;

	public static $prefix;

	public function __construct(NPCPlugin $plugin){
		$this->plugin = $plugin;

		$this->lang = $plugin->getConfig()->getNested("lang", "eng");

		$plugin->saveResource($this->lang . ".yml");

		$this->data = yaml_parse(file_get_contents($plugin->getDataFolder() . $this->lang . ".yml"));

		if(!is_array($this->data)){
			throw new InvalidLanguageException("Language name with " . $this->lang . "is not found.");
		}

		self::$prefix = $this->data["plugin.prefix"];
	}

	public function translateLanguage(string $str, array $params = []) : string{
		$string = $this->data[$str];
		foreach($params as $i => $param){
			$string = str_replace("{%" . $i . "}", $param, $this->data[$str]);
		}

		return $string;
	}

	public function getRealMessage(string $str) : string{
		return $this->data[$str];
	}
}