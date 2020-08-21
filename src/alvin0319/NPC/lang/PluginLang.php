<?php
declare(strict_types=1);

namespace alvin0319\NPC\lang;

use alvin0319\NPC\NPCPlugin;
use alvin0319\NPC\util\InvalidLanguageException;

use function file_get_contents;
use function is_array;
use function str_replace;
use function unlink;
use function yaml_parse;

class PluginLang{

	/** @var array */
	protected $data;

	/** @var string */
	protected $lang;

	/** @var NPCPlugin */
	protected $plugin;

	/** @var string */
	public static $prefix;

	/**
	 * PluginLang constructor.
	 *
	 * @param NPCPlugin $plugin
	 *
	 * @throws InvalidLanguageException
	 */
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

	public function saveNewLang(){
		unlink($this->plugin->getDataFolder() . $this->lang . ".yml");
		$this->plugin->saveResource($this->lang . ".yml");
	}

	/**
	 * @param string $str
	 * @param array  $params
	 *
	 * @return string
	 */
	public function translateLanguage(string $str, array $params = []) : string{
		$string = $this->data[$str];
		foreach($params as $i => $param){
			$string = str_replace("{%" . $i . "}", $param, $this->data[$str]);
		}

		return $string;
	}

	/**
	 * @param string $str
	 *
	 * @return string
	 */
	public function getRealMessage(string $str) : string{
		return $this->data[$str];
	}
}