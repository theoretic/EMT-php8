<?php
/*
Evgeny Muravjev Typograph, http://mdash.ru
class EMT_Tret_OptAlign
php8 version
AT / atis.pro
28.12.23
*/

namespace EMT;

class EMT_Tret_OptAlign extends EMT_Tret
{
	public $classes = [
			'oa_obracket_sp_s' => "margin-right:0.3em;",
			"oa_obracket_sp_b" => "margin-left:-0.3em;",
			"oa_obracket_nl_b" => "margin-left:-0.3em;",
			"oa_comma_b"	  => "margin-right:-0.2em;",
			"oa_comma_e"	  => "margin-left:0.2em;",
			'oa_oquote_nl' => "margin-left:-0.44em;",
			'oa_oqoute_sp_s' => "margin-right:0.44em;",
			'oa_oqoute_sp_q' => "margin-left:-0.44em;",
		];
	/**
	 * Базовые параметры тофа
	 *
	 * @var array
	 */
	public $title = "Оптическое выравнивание";
	public $rules = [
		'oa_oquote' => [
				'description'	=> 'Оптическое выравнивание открывающей кавычки',
				//'disabled'	 => true,
				'pattern' 		=> [
							'/([a-zа-яё\-]{3,})(\040|\&nbsp\;|\t)(\&laquo\;)/uie',
							'/(\n|\r|^)(\&laquo\;)/ei'
						],
				'replacement' 	=> [
							'$m[1] . $this->tag($m[2], "span", ["class"=>"oa_oqoute_sp_s"]) . $this->tag($m[3], "span", ["class"=>"oa_oqoute_sp_q"])',
							'$m[1] . $this->tag($m[2], "span", ["class"=>"oa_oquote_nl"])',
						],
			],
		'oa_oquote_extra' => [
			'description'	=> 'Оптическое выравнивание кавычки',
			//'disabled'	 => true,
			'function'	=> 'oaquote_extra'
		],
		'oa_obracket_coma' => [
				'description'	=> 'Оптическое выравнивание для пунктуации (скобка)',
				//'disabled'	 => true,
				'pattern' 		=> [
							'/(\040|\&nbsp\;|\t)\(/ei',
							'/(\n|\r|^)\(/ei',
							//'/([а-яёa-z0-9]+)\,(\040+)/iue',
						],
				'replacement' 	=> [
							'$this->tag($m[1], "span", ["class"=>"oa_obracket_sp_s"]) . $this->tag("(", "span", ["class"=>"oa_obracket_sp_b"])',
							'$m[1] . $this->tag("(", "span", ["class"=>"oa_obracket_nl_b"])',
							//'$m[1] . $this->tag(",", "span", ["class"=>"oa_comma_b")) . $this->tag(" ", "span", ["class"=>"oa_comma_e"])',
						],
			],

		];

	/**
	 * Если стоит открывающая кавычка после <p> надо делать её висячей
	 *
	 * @return  void
	 */
	protected function oaquote_extra()
	{
		$this->_text = $this->preg_replace_e(
				'/(<' .self::BASE64_PARAGRAPH_TAG . '>)([\040\t]+)?(\&laquo\;)/e', 
				'$m[1] . $this->tag($m[3], "span", ["class"=>"oa_oquote_nl")]',
				$this->_text);
	}

}