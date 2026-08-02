<?php
declare(strict_types=1);
/*
Evgeny Muravjev Typograph, http://mdash.ru
class EMT_Tret
php8 version
AT / atis.pro
28.12.23
*/

/**
 * Базовый класс для группы правил обработки текста
 * Класс группы должен наследовать, данный класс и задавать
 * в нём EMT_Tret::rules и EMT_Tret::$name
 * 
 */

namespace EMT;

class EMT_Tret {
	/**
	 * Набор правил в данной группе, который задан изначально
	 * Его можно менять динамически добавляя туда правила с помощью put_rule
	 *
	 * @var unknown_type
	 */
	public array  $rules = [];
	public string $title = '';

	private array  $disabled = [];
	private array  $enabled  = [];
	protected string $_text = '';
	public bool  $logging = false;
	public array $logs   = [];
	public array $errors = [];
	public bool  $debug_enabled = false;
	public array $debug_info    = [];

	private int         $use_layout          = EMT_Lib::LAYOUT_STYLE;
	private bool        $use_layout_set      = false;
	private string|false $class_layout_prefix = false;
	public array $class_names = [];
	public array $classes     = [];
	public array $settings    = [];
	/** Reference back to the owning EMT_Base instance */
	public ?EMT_Base $EMT = null;
	/**
	 * Защищенные теги
	 * 
	 * @todo привязать к методам из Jare_Typograph_Tool
	 */
	const BASE64_PARAGRAPH_TAG = 'cA==='; // p
	const BASE64_BREAKLINE_TAG = 'YnIgLw==='; // br / (с пробелом и слэшем)
	const BASE64_NOBR_OTAG = 'bm9icg==='; // nobr
	const BASE64_NOBR_CTAG = 'L25vYnI=='; // /nobr
	/**
	 * Типы кавычек
	 */
	const QUOTE_FIRS_OPEN = '&laquo;';
	const QUOTE_FIRS_CLOSE = '&raquo;';
	const QUOTE_CRAWSE_OPEN = '&bdquo;';
	const QUOTE_CRAWSE_CLOSE = '&ldquo;';
	/** Предел повторов для правил с 'cycled' => true */
	const MAX_CYCLES = 20;

	private function log($str, $data = null)
	{
		if(!$this->logging) return;
		$this->logs[] = ['info' => $str, 'data' => $data];
	}
	private function error($info, $data = null)
	{
		$this->errors[] = ['info' => $info, 'data' => $data];
		$this->log('ERROR: '. $info , $data);
	}
	public function debug($place, &$after_text)
	{
		if(!$this->debug_enabled) return;
		$this->debug_info[] = [
				'place' => $place,
				'text'  => $after_text,
			];
	}

	/**
	 * Установить режим разметки для данного Трэта если не было раньше установлено,
	 *	EMT_Lib::LAYOUT_STYLE - с помощью стилей
	 *	EMT_Lib::LAYOUT_CLASS - с помощью классов
	 *
	 * @param int $kind
	 */
	public function set_tag_layout_ifnotset($layout)
	{
		if($this->use_layout_set) return;
		$this->use_layout = $layout;
	}
	/**
	 * Установить режим разметки для данного Трэта,
	 *	EMT_Lib::LAYOUT_STYLE - с помощью стилей
	 *	EMT_Lib::LAYOUT_CLASS - с помощью классов
	 *	EMT_Lib::LAYOUT_STYLE|EMT_Lib::LAYOUT_CLASS - оба метода
	 *
	 * @param int $kind
	 */
	public function set_tag_layout($layout = EMT_Lib::LAYOUT_STYLE)
	{
		$this->use_layout = $layout;
		$this->use_layout_set = true;
	}
	public function set_class_layout_prefix($prefix)
	{
		$this->class_layout_prefix = $prefix;
	}

	public function debug_on()
	{
		$this->debug_enabled = true;
	}
	public function log_on()
	{
		$this->logging = true;
	}

	private function getmethod($name)
	{
		if(!$name) return false;
		if(!method_exists($this, $name)) return false;
		return [$this, $name];
	}
	private function _pre_parse()
	{
		$this->pre_parse();
		foreach($this->rules as $rule)
		{
			if(!isset($rule['init'])) continue;
			$m = $this->getmethod($rule['init']);
			if(!$m) continue;
			call_user_func($m);
		}
	}
	private function _post_parse()
	{
		foreach($this->rules as $rule)
		{
			if(!isset($rule['deinit'])) continue;
			$m = $this->getmethod($rule['deinit']);
			if(!$m) continue;
			call_user_func($m);
		}
		$this->post_parse();
	}
	private function apply_rule($rule)
	{
		$name = $rule['id'];
		//$this->log("Правило $name", "Применяем правило");
		$disabled = (isset($this->disabled[$rule['id']]) && $this->disabled[$rule['id']]) || ((isset($rule['disabled']) && $rule['disabled']) && !(isset($this->enabled[$rule['id']]) && $this->enabled[$rule['id']]));
		if($disabled)
		{
			$this->log("Правило $name", "Правило отключено" . ((isset($rule['disabled']) && $rule['disabled'])? " (по умолчанию)" : ""));
			return;
		}
		if(isset($rule['function']) && $rule['function'])
		{
			if(!(isset($rule['pattern']) &&  $rule['pattern']))
			{
				if(method_exists($this, $rule['function']))
				{
					$this->log("Правило $name", "Используется метод ".$rule['function']." в правиле");
					call_user_func( [$this, $rule['function']] );
					return;
				}
				if(function_exists($rule['function']))
				{
					$this->log("Правило $name", "Используется функция ".$rule['function']." в правиле");

					call_user_func($rule['function']);
					return;
				}

				$this->error('Функция '.$rule['function'].' из правила '.$rule['id']. " не найдена");
				return ;
			} else {
				if(preg_match("/^[a-z_0-9]+$/i", $rule['function']))
				{
					if(method_exists($this, $rule['function']))
					{
						$this->log("Правило $name", "Замена с использованием preg_replace_callback с методом ".$rule['function']."");

						$this->_text = preg_replace_callback($rule['pattern'], [$this, $rule['function']], $this->_text);
						return;
					} 
					if(function_exists($rule['function']))
					{
						$this->log("Правило $name", "Замена с использованием preg_replace_callback с функцией ".$rule['function']."");

						$this->_text = preg_replace_callback($rule['pattern'], $rule['function'], $this->_text);
						return;
					}
					$this->error('Функция '.$rule['function'].' из правила '.$rule['id']. " не найдена");
				} else {
					$this->_text = preg_replace_callback($rule['pattern'], $rule['function'], $this->_text);
					$this->log('Замена с использованием preg_replace_callback с инлайн функцией из правила '.$rule['id']);
					return;
				}
				return ;
			}
		}

		if(isset($rule['simple_replace']) && $rule['simple_replace'])
		{
			if(isset($rule['case_sensitive']) && $rule['case_sensitive'])
			{
				$this->log("Правило $name", "Простая замена с использованием str_replace");
				$this->_text = str_replace($rule['pattern'], $rule['replacement'], $this->_text);
				return;
			}
			$this->log("Правило $name", "Простая замена с использованием str_ireplace");
			$this->_text = str_ireplace($rule['pattern'], $rule['replacement'], $this->_text);
			return;
		}

		$pattern = $rule['pattern'];
		if(is_string($pattern)) $pattern = [$pattern];
		$eval = false;
		foreach($pattern as $patt)
		{
			$chr = substr($patt,0,1);
			$preg_arr = explode($chr, $patt);
			if(str_contains($preg_arr[count($preg_arr)-1], 'e'))
			{
				$eval = true;
				break;
			}
		}
		$cycled = isset($rule['cycled']) && $rule['cycled'];

		if(!$eval)
		{
			$this->log("Правило $name", "Замена с использованием preg_replace");
			$this->replace_cycled($rule['pattern'], $rule['replacement'], $cycled, $name);
			return;
		}

		$this->log("Правило $name", "Замена с использованием preg_replace_callback вместо eval");
		$k = 0;
		foreach($pattern as $patt)
		{
			$repl = is_string($rule['replacement']) ? $rule['replacement'] : $rule['replacement'][$k];

			$chr = substr($patt,0,1);
			$preg_arr = explode($chr, $patt);
			if(str_contains($preg_arr[count($preg_arr)-1], 'e')) // eval система
			{
				$preg_arr[count($preg_arr)-1] = str_replace("e","",$preg_arr[count($preg_arr)-1]);
				$patt = implode($chr, $preg_arr);
				$repl = $this->repl_cache[$repl] ??= $this->make_repl_closure($repl);
			}
			$this->replace_cycled($patt, $repl, $cycled, $name);
			$k++;
		}
	}

	/**
	 * Применить замену к $this->_text, при $cycled — повторять пока замены происходят.
	 *
	 * Счётчик замен из preg_replace() заменяет отдельный preg_match(): цикл
	 * решает, надо ли повторять, не сканируя текст второй раз.
	 *
	 * @param string|string[] $pattern
	 * @param string|\Closure $replacement строка-замена либо callback для preg_replace_callback
	 */
	private function replace_cycled($pattern, $replacement, bool $cycled, string $name): void
	{
		$iter = 0;
		do {
			$result = $replacement instanceof \Closure
				? preg_replace_callback($pattern, $replacement, $this->_text, -1, $count)
				: preg_replace($pattern, $replacement, $this->_text, -1, $count);

			if($result === null)
			{
				// PCRE упёрся в лимит (backtrack/recursion) — оставляем текст как есть
				$this->error("Правило $name: ошибка PCRE, замена пропущена", preg_last_error_msg());
				return;
			}

			$this->_text = $result;
		} while($cycled && $count > 0 && ++$iter < self::MAX_CYCLES);
	}

	protected function preg_replace_e($pattern, $replacement, $text)
	{
		$chr = substr($pattern,0,1);
		$preg_arr = explode($chr, $pattern);
		if(!str_contains($preg_arr[count($preg_arr)-1], 'e')) return preg_replace($pattern, $replacement, $text);
		$preg_arr[count($preg_arr)-1] = str_replace("e","",$preg_arr[count($preg_arr)-1]);
		$patt = implode($chr, $preg_arr);
		$cb = $this->repl_cache[$replacement] ??= $this->make_repl_closure($replacement);
		return preg_replace_callback($patt, $cb, $text);
	}
	/** @var array<string, \Closure> Cache of compiled replacement closures keyed by replacement expression */
	private array $repl_cache = [];

	/**
	 * Compile a replacement expression string into a reusable Closure (cached).
	 *
	 * SECURITY: the expression is eval()'d. This replaces the legacy /e regex flag
	 * and is safe for the rule sets shipped with this package, but any replacement
	 * reaching put_rule()/set_rule() from untrusted input is arbitrary code execution.
	 */
	private function make_repl_closure(string $repl): \Closure
	{
		// phpcs:ignore Squiz.PHP.Eval.Discouraged
		return eval('return function(array $m) { return ' . ($repl !== '' ? $repl : '""') . '; };');
	}
	private function _apply($list)
	{
		$this->errors = [];
		$this->_pre_parse();

		$this->log("Применяется набор правил", implode(",",$list));

		$rulelist = [];
		foreach($list as $k)
		{
			$rule = $this->rules[$k];
			$rule['id']	= $k;
			$rule['order'] = $rule['order'] ?? 5;
			$rulelist[] = $rule;
		}
		// usort в PHP 8 стабилен, поэтому правила с одинаковым order
		// сохраняют порядок объявления
		usort($rulelist, fn($a, $b) => $a['order'] <=> $b['order']);

		foreach($rulelist as $rule)
		{
			$this->apply_rule($rule);
			$this->debug($rule['id'], $this->_text);
		}

		$this->_post_parse();
	}

	/**
	 * Создание защищенного тега с содержимым
	 *
	 * @see 	EMT_lib::build_safe_tag
	 * @param 	string $content
	 * @param 	string $tag
	 * @param 	array $attribute
	 * @return 	string
	 */
	protected function tag($content, $tag = 'span', $attribute = [])
	{
		if(isset($attribute['class']))
		{
			$classname = $attribute['class'];
			if($classname == "nowrap")
			{
				if(!$this->is_on('nowrap'))
				{
					$tag = "nobr";
					$attribute = [];
					$classname = "";
				}
			}
			if(isset($this->classes[$classname]))
			{
				$style_inline = $this->classes[$classname];
				if($style_inline) $attribute['__style'] = $style_inline;
			}
			$classname = $this->class_names[$classname] ?? $classname;
			$classname = ($this->class_layout_prefix ?: '') . $classname;
			$attribute['class'] = $classname;
		}

		return EMT_Lib::build_safe_tag($content, $tag, $attribute, $this->use_layout);
	}

	/**
	 * Добавить правило в группу
	 *
	 * @param string $name
	 * @param array $params
	 */
	public function put_rule($name, $params)
	{
		$this->rules[$name] = $params; 
		return $this;
	}
	/**
	 * Отключить правило, в обработке
	 *
	 * @param string $name
	 */
	public function disable_rule($name)
	{
		$this->disabled[$name] = true;
		unset($this->enabled[$name]);
	}
	/**
	 * Включить правило
	 *
	 * @param string $name
	 */
	public function enable_rule($name)
	{
		$this->enabled[$name] = true;
		unset($this->disabled[$name]);
	}
	/**
	 * Добавить настройку в трет
	 *
	 * @param string $key ключ
	 * @param mixed $value значение
	 */
	public function set($key, $value)
	{
		$this->settings[$key] = $value;
	}
	/**
	 * Установлена ли настройка
	 *
	 * @param string $key
	 */
	public function is_on($key)
	{
		if(!isset($this->settings[$key])) return false;
		$kk = $this->settings[$key];
		return (is_string($kk) && strtolower($kk)==="on") || $kk === "1" || $kk === true || $kk === 1;
	}
	/**
	 * Получить строковое значение настройки
	 *
	 * @param unknown_type $key
	 * @return unknown
	 */
	public function ss($key)
	{
		if(!isset($this->settings[$key])) return "";
		return strval($this->settings[$key]);
	}
	/**
	 * Добавить настройку в правило
	 *
	 * @param string $rulename идентификатор правила 
	 * @param string $key ключ
	 * @param mixed $value значение
	 */
	public function set_rule($rulename, $key, $value)
	{
		$this->rules[$rulename][$key] = $value;
	}
	/**
	 * Включить правила, согласно списку
	 *
	 * @param array $list список правил
	 * @param boolean $disable выкллючить их или включить
	 * @param boolean $strict строго, т.е. те которые не в списку будут тоже обработаны
	 */
	public function activate($list,$disable =false, $strict = true)
	{
		if(!is_array($list)) return ;

		foreach($list as $rulename)
		{
			if($disable) $this->disable_rule($rulename); else $this->enable_rule($rulename);
		}

		if($strict)
		{
			foreach($this->rules as $rulename => $v)
			{
				if(in_array($rulename, $list)) continue;
				if(!$disable) $this->disable_rule($rulename); else $this->enable_rule($rulename);
			}
		}
	}
	public function set_text(&$text)
	{
		$this->_text = &$text;
		$this->debug_info = [];
		$this->logs = [];
	}

	/**
	 * Применить к тексту
	 *
	 * @param string $text - текст к которому применить
	 * @param mixed $list - список правил, null - все правила
	 * @return string
	 */
	public function apply($list = null)
	{
		if(is_string($list)) $rlist = [$list];
		elseif(is_array($list)) $rlist = $list;
		else $rlist = array_keys($this->rules);
		$this->_apply($rlist);
		return $this->_text;
	}


	/**
	 * Код, выполняем до того, как применить правила
	 *
	 */
	public function pre_parse()
	{
	}
	/**
	 * После выполнения всех правил, выполняется этот метод
	 *
	 */
	public function post_parse()
	{
	}

}
