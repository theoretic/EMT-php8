<?php
/*
Evgeny Muravjev Typograph, http://mdash.ru
class EMT_Tret_Date
php8 version
AT / atis.pro
28.12.23
*/

namespace EMT;

class EMT_Tret_Date extends EMT_Tret
{
	public $title = "Даты и дни";
	public $classes = [
			'nowrap'		 => 'word-spacing:nowrap;',
			];
	public $rules = [
		'years' => [
				'description'	=> 'Установка тире и пробельных символов в периодах дат',
				'pattern' 		=> '/(с|по|период|середины|начала|начало|конца|конец|половины|в|между|\([cс]\)|\&copy\;)(\s+|\&nbsp\;)([\d]{4})(-|\&mdash\;|\&minus\;)([\d]{4})(( |\&nbsp\;)?(г\.г\.|гг\.|гг|г\.|г)([^а-яёa-z]))?/eui',
				'replacement' 	=> '$m[1].$m[2].  (intval($m[3])>=intval($m[5])? $m[3].$m[4].$m[5] : $m[3]."&mdash;".$m[5]) . (isset($m[6])? "&nbsp;гг.":"").(isset($m[9])?$m[9]:"")'
			],
		'mdash_month_interval' => [
				'description'	=> 'Расстановка тире и объединение в неразрывные периоды месяцев',
				'disabled'		=> true,
				'pattern' 		=> '/((январ|феврал|сентябр|октябр|ноябр|декабр)([ьяюе]|[её]м)|(апрел|июн|июл)([ьяюе]|ем)|(март|август)([ауе]|ом)?|ма[йяюе]|маем)\-((январ|феврал|сентябр|октябр|ноябр|декабр)([ьяюе]|[её]м)|(апрел|июн|июл)([ьяюе]|ем)|(март|август)([ауе]|ом)?|ма[йяюе]|маем)/iu',
				'replacement' 	=> '\1&mdash;\8'
			],
		'nbsp_and_dash_month_interval' => [
				'description'	=> 'Расстановка тире и объединение в неразрывные периоды дней',
				'disabled'	 => true,
				'pattern' 		=> '/([^\>]|^)(\d+)(\-|\&minus\;|\&mdash\;)(\d+)( |\&nbsp\;)(января|февраля|марта|апреля|мая|июня|июля|августа|сентября|октября|ноября|декабря)([^\<]|$)/ieu',
				'replacement' 	=> '$m[1].$this->tag($m[2]."&mdash;".$m[4]." ".$m[6],"span", ["class"=>"nowrap"]).$m[7]'
			],
		'nobr_year_in_date' => [
				'description'	=> 'Привязка года к дате',
				'pattern' 		=> [
					'/(\s|\&nbsp\;)([0-9]{2}\.[0-9]{2}\.([0-9]{2})?[0-9]{2})(\s|\&nbsp\;)?г(\.|\s|\&nbsp\;)/eiu',
					'/(\s|\&nbsp\;)([0-9]{2}\.[0-9]{2}\.([0-9]{2})?[0-9]{2})(\s|\&nbsp\;|\.(\s|\&nbsp\;|$)|$)/eiu',
					],
				'replacement' 	=> [
					'$m[1].$this->tag($m[2]." г.","span", ["class"=>"nowrap"]).($m[5]==="."?"":" "]',
					'$m[1].$this->tag($m[2],"span", ["class"=>"nowrap")].$m[4]',
					],
			],
		'space_posle_goda' => [
				'description'	=> 'Пробел после года',
				'pattern' 		=> '/(^|\040|\&nbsp\;)([0-9]{3,4})(год([ауе]|ом)?)([^a-zа-яё]|$)/ui', 
				'replacement' 	=> '\1\2 \3\5'
			],
		'nbsp_posle_goda_abbr' => [
				'description'	=> 'Пробел после года',
				'pattern' 		=> '/(^|\040|\&nbsp\;|\"|\&laquo\;)([0-9]{3,4})[ ]?(г\.)([^a-zа-яё]|$)/ui', 
				'replacement' 	=> '\1\2&nbsp;\3\4'
			],

		];
}