<?php
/*
Evgeny Muravjev Typograph, http://mdash.ru
class EMT_Tret_Dash
php8 version
AT / atis.pro
28.12.23
*/

namespace EMT;

class EMT_Tret_Dash extends EMT_Tret
{
	public $title = "Дефисы и тире";
	public $rules = [
		'double_minus_to_html_mdash' => [
				'description'	=> 'Замена двойного минуса на html конструкцию',
				'pattern' 		=> '/ [\-]{2} /iu',
				'replacement' 	=> ' &mdash; '
			],
		'mdash_symbol_to_html_mdash' => [
				'description'	=> 'Замена символа тире на html конструкцию',
				'pattern' 		=> '/ — /iu',
				'replacement' 	=> ' &mdash; '
			],
		'mdash' => [
				'description'	=> 'Тире после кавычек, скобочек, пунктуации',
				'pattern' 		=> [
						'/([a-zа-яё0-9]+|\,|\:|\)|\&(ra|ld)quo\;|\|\"|\>)(\040|\t)(—|\-|\&mdash\;)(\s|$|\<)/ui', 
						'/(\,|\:|\)|\")(—|\-|\&mdash\;)(\s|$|\<)/ui', 
						],
				'replacement' 	=> [
						'\1&nbsp;&mdash;\5',
						'\1&nbsp;&mdash;\3',
						],
			],
		'mdash_2' => [
				'description'	=> 'Тире после переноса строки',
				'pattern' 		=> '/(\n|\r|^|\>)(\-|\&mdash\;)(\t|\040)/',
				'replacement' 	=> '\1&mdash;&nbsp;'
			],
		'mdash_3' => [
				'description'	=> 'Тире после знаков восклицания, троеточия и прочее',
				'pattern' 		=> '/(\.|\!|\?|\&hellip\;)(\040|\t|\&nbsp\;)(\-|\&mdash\;)(\040|\t|\&nbsp\;)/',
				'replacement' 	=> '\1 &mdash;&nbsp;'
			],
		'iz_za_pod' => [
				'description'	=> 'Расстановка дефисов между из-за, из-под',
				'pattern' 		=> '/(\s|\&nbsp\;|\>|^)(из)(\040|\t|\&nbsp\;)\-?(за|под)([\.\,\!\?\:\;]|\040|\&nbsp\;)/uie',
				'replacement' 	=> '($m[1] == "&nbsp;" ? " " : $m[1]) . $m[2]."-".$m[4] . ($m[5] == "&nbsp;"? " " : $m[5])'
			],
		'to_libo_nibud' => [
				'description'	=> 'Автоматическая простановка дефисов в обезличенных местоимениях и междометиях',
				'cycled'		=> true,
				'pattern' 		=> '/(\s|^|\&nbsp\;|\>)(кто|кем|когда|зачем|почему|как|что|чем|где|чего|кого)\-?(\040|\t|\&nbsp\;)\-?(то|либо|нибудь)([\.\,\!\?\;]|\040|\&nbsp\;|$)/uie',
				'replacement' 	=> '($m[1] == "&nbsp;" ? " " : $m[1]) . $m[2]."-".$m[4] . ($m[5] == "&nbsp;"? " " : $m[5])'
			],
		'koe_kak' => [
				'description'	=> 'Кое-как, кой-кого, все-таки',
				'cycled'		=> true,
				'pattern' 		=> [
						'/(\s|^|\&nbsp\;|\>)(кое)\-?(\040|\t|\&nbsp\;)\-?(как)([\.\,\!\?\;]|\040|\&nbsp\;|$)/uie',
						'/(\s|^|\&nbsp\;|\>)(кой)\-?(\040|\t|\&nbsp\;)\-?(кого)([\.\,\!\?\;]|\040|\&nbsp\;|$)/uie',
						'/(\s|^|\&nbsp\;|\>)(вс[её])\-?(\040|\t|\&nbsp\;)\-?(таки)([\.\,\!\?\;]|\040|\&nbsp\;|$)/uie',
						],
				'replacement' 	=> '($m[1] == "&nbsp;" ? " " : $m[1]) . $m[2]."-".$m[4] . ($m[5] == "&nbsp;"? " " : $m[5])'
			],
		'ka_de_kas' => [
				'description'	=> 'Расстановка дефисов с частицами ка, де, кась',
				'disabled'		=> true,
				'pattern' 		=> [
						'/(\s|^|\&nbsp\;|\>)([а-яё]+)(\040|\t|\&nbsp\;)(ка)([\.\,\!\?\;]|\040|\&nbsp\;|$)/uie',
						'/(\s|^|\&nbsp\;|\>)([а-яё]+)(\040|\t|\&nbsp\;)(де)([\.\,\!\?\;]|\040|\&nbsp\;|$)/uie',
						'/(\s|^|\&nbsp\;|\>)([а-яё]+)(\040|\t|\&nbsp\;)(кась)([\.\,\!\?\;]|\040|\&nbsp\;|$)/uie',
						],
				'replacement' 	=> '($m[1] == "&nbsp;" ? " " : $m[1]) . $m[2]."-".$m[4] . ($m[5] == "&nbsp;"? " " : $m[5])'
			],

		];
}
