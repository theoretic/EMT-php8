<?
/*
Evgeny Muravjev Typograph, http://mdash.ru
class EMT_Tret_Nobr
php8 version
AT / atis.pro
28.12.23
*/

namespace EMT;

class EMT_Tret_Nobr extends EMT_Tret
{
	public $title = "Неразрывные конструкции";
	public $classes = [
			'nowrap'		 => 'word-spacing:nowrap;',
			];
	public $rules = [

		'super_nbsp' => [
				'description'	=> 'Привязка союзов и предлогов к написанным после словам',
				'pattern' 		=> '/(\s|^|\&(la|bd)quo\;|\>|\(|\&mdash\;\&nbsp\;)([a-zа-яё]{1,2}\s+)([a-zа-яё]{1,2}\s+)?([a-zа-яё0-9\-]{2,}|[0-9])/ieu',
				'replacement' 	=> '$m[1] . trim($m[3]) . "&nbsp;" . ($m[4] ? trim($m[4]) . "&nbsp;" : "") . $m[5]'
			],
		'nbsp_in_the_end' => [
				'description'	=> 'Привязка союзов и предлогов к предыдущим словам в случае конца предложения',
				'pattern' 		=> '/([a-zа-яё0-9\-]{3,}) ([a-zа-яё]{1,2})\.( [A-ZА-ЯЁ]|$)/u', 
				'replacement' 	=> '\1&nbsp;\2.\3'
			],
		'phone_builder' => [
				'description'	=> 'Объединение в неразрывные конструкции номеров телефонов',
				'pattern' 		=> 
					[
						'/([^\d\+]|^)([\+]?[0-9]{1,3})( |\&nbsp\;|\&thinsp\;)([0-9]{3,4}|\([0-9]{3,4}\))( |\&nbsp\;|\&thinsp\;)([0-9]{2,3})(-|\&minus\;)([0-9]{2})(-|\&minus\;)([0-9]{2})([^\d]|$)/e',
						'/([^\d\+]|^)([\+]?[0-9]{1,3})( |\&nbsp\;|\&thinsp\;)([0-9]{3,4}|[0-9]{3,4})( |\&nbsp\;|\&thinsp\;)([0-9]{2,3})(-|\&minus\;)([0-9]{2})(-|\&minus\;)([0-9]{2})([^\d]|$)/e',
					],
				'replacement'	=> 
					[
						'$m[1]  .(($m[1] == ">" || $m[11] == "<") ? $m[2]." ".$m[4]." ".$m[6]."-".$m[8]."-".$m[10] :$this->tag($m[2]." ".$m[4]." ".$m[6]."-".$m[8]."-".$m[10], "span", ["class"=>"nowrap"))  ].$m[11]',
						'$m[1]  .(($m[1] == ">" || $m[11] == "<") ? $m[2]." ".$m[4]." ".$m[6]."-".$m[8]."-".$m[10] :$this->tag($m[2]." ".$m[4]." ".$m[6]."-".$m[8]."-".$m[10], "span", ["class"=>"nowrap"))  ].$m[11]',
					],
			],
		'phone_builder_v2' => [
				'description'	=> 'Дополнительный формат номеров телефонов',
				'pattern' 		=> '/([^\d]|^)\+\s?([0-9]{1})\s?\(([0-9]{3,4})\)\s?(\d{3})(\d{2})(\d{2})([^\d]|$)/ie',
				'replacement'	=> '$m[1].$this->tag("+".$m[2]." ".$m[3]." ".$m[4]."-".$m[5]."-".$m[6], "span",  ["class" => "nowrap")].$m[7]',
			],
		'ip_address' => [
				'description'	=> 'Объединение IP-адресов',
				'pattern' 		=> '/(\s|\&nbsp\;|^)(\d{0,3}\.\d{0,3}\.\d{0,3}\.\d{0,3})/ie', 
				'replacement' 	=> '$m[1] . $this->nowrap_ip_address($m[2])'
			],
		'dots_for_surname_abbr' => [
				'disabled'	 => true,
				'description'	=> 'Простановка точек к инициалам у фамилии',
				'pattern' 		=> 
					[
						'/(\s|^|\.|\,|\;|\:|\?|\!|\&nbsp\;)([А-ЯЁ])\.?(\s|\&nbsp\;)?([А-ЯЁ])(\s|\&nbsp\;)([А-ЯЁ][а-яё]+)(\s|$|\.|\,|\;|\:|\?|\!|\&nbsp\;)/ue',
						'/(\s|^|\.|\,|\;|\:|\?|\!|\&nbsp\;)([А-ЯЁ][а-яё]+)(\s|\&nbsp\;)([А-ЯЁ])\.?(\s|\&nbsp\;)?([А-ЯЁ])\.?(\s|$|\.|\,|\;|\:|\?|\!|\&nbsp\;)/ue',
					],
				'replacement' 	=> 
					[
						'$m[1].$this->tag($m[2].". ".$m[4].". ".$m[6], "span",  ["class" => "nowrap")].$m[7]',
						'$m[1].$this->tag($m[2]." ".$m[4].". ".$m[6].".", "span",  ["class" => "nowrap")].$m[7]',
					],
			],
		'spaces_nobr_in_surname_abbr' => [
				'description'	=> 'Привязка инициалов к фамилиям',
				'pattern' 		=> 
					[
						'/(\s|^|\.|\,|\;|\:|\?|\!|\&nbsp\;)([А-ЯЁ])\.(\s|\&nbsp\;)?([А-ЯЁ])\.(\s|\&nbsp\;)?([А-ЯЁ][а-яё]+)(\s|$|\.|\,|\;|\:|\?|\!|\&nbsp\;)/ue',
						'/(\s|^|\.|\,|\;|\:|\?|\!|\&nbsp\;)([А-ЯЁ][а-яё]+)(\s|\&nbsp\;)([А-ЯЁ])\.(\s|\&nbsp\;)?([А-ЯЁ])\.(\s|$|\.|\,|\;|\:|\?|\!|\&nbsp\;)/ue',
						'/(\s|^|\.|\,|\;|\:|\?|\!|\&nbsp\;)([А-ЯЁ])(\s|\&nbsp\;)?([А-ЯЁ])(\s|\&nbsp\;)([А-ЯЁ][а-яё]+)(\s|$|\.|\,|\;|\:|\?|\!|\&nbsp\;)/ue',
						'/(\s|^|\.|\,|\;|\:|\?|\!|\&nbsp\;)([А-ЯЁ][а-яё]+)(\s|\&nbsp\;)([А-ЯЁ])(\s|\&nbsp\;)?([А-ЯЁ])(\s|$|\.|\,|\;|\:|\?|\!|\&nbsp\;)/ue',
						//'/(\s|^|\.|\,|\;|\:|\?|\!|\&nbsp\;)([A-Z])\.?(\s|\&nbsp\;)?([A-Z])(\.(\s|\&nbsp\;)?|(\s|\&nbsp\;))([A-Z][a-z]+)(\s|$|\.|\,|\;|\:|\?|\!|\&nbsp\;)/ue',
						//'/(\s|^|\.|\,|\;|\:|\?|\!|\&nbsp\;)([A-Z][a-z]+)(\s|\&nbsp\;)([A-Z])\.?(\s|\&nbsp\;)?([A-Z])\.?(\s|$|\.|\,|\;|\:|\?|\!|\&nbsp\;)/ue',
					],
				'replacement' 	=> 
					[
						'$m[1].$this->tag($m[2].". ".$m[4].". ".$m[6], "span",  ["class" => "nowrap")].$m[7]',
						'$m[1].$this->tag($m[2]." ".$m[4].". ".$m[6].".", "span",  ["class" => "nowrap")].$m[7]',
						'$m[1].$this->tag($m[2].(isset($m[3])? " " : "" ).$m[4].(isset($m[5])? " " : "" ).$m[6], "span",  ["class" => "nowrap")].$m[7]',
						'$m[1].$this->tag($m[2]." ".$m[4].(isset($m[5])? " " : "" ).$m[6], "span",  ["class" => "nowrap")].$m[7]',
						//'$m[1].$this->tag($m[2].". ".$m[4].". ".$m[8], "span",  ["class" => "nowrap")].$m[9]',
						//'$m[1].$this->tag($m[2]." ".$m[4].". ".$m[6].".", "span",  ["class" => "nowrap")].$m[7]',
					],
			],
		'nbsp_before_particle' => [
				'description'	=> 'Неразрывный пробел перед частицей',
				'pattern' 		=> '/(\040|\t)+(ли|бы|б|же|ж)(\&nbsp\;|\.|\,|\:|\;|\&hellip\;|\?|\s)/iue', 
				'replacement' 	=> '"&nbsp;".$m[2] . ($m[3] == "&nbsp;" ? " " : $m[3])'
			],
		'nbsp_v_kak_to' => [
				'description'	=> 'Неразрывный пробел в как то',
				'pattern' 		=> '/как то\:/ui', 
				'replacement' 	=> 'как&nbsp;то:'
			],
		'nbsp_celcius' => [
				'description'	=> 'Привязка градусов к числу',
				'pattern' 		=> '/(\s|^|\>|\&nbsp\;)(\d+)( |\&nbsp\;)?(°|\&deg\;)(C|С)(\s|\.|\!|\?|\,|$|\&nbsp\;|\;)/iu', 
				'replacement' 	=> '\1\2&nbsp;\4C\6'
			],
		'hyphen_nowrap_in_small_words' => [
				'description'	=> 'Обрамление пятисимвольных слов разделенных дефисом в неразрывные блоки',
				'disabled'	 => true,
				'cycled'		=> true,
				'pattern' 		=> '/(\&nbsp\;|\s|\>|^)([a-zа-яё]{1}\-[a-zа-яё]{4}|[a-zа-яё]{2}\-[a-zа-яё]{3}|[a-zа-яё]{3}\-[a-zа-яё]{2}|[a-zа-яё]{4}\-[a-zа-яё]{1}|когда\-то|кое\-как|кой\-кого|вс[её]\-таки|[а-яё]+\-(кась|ка|де))(\s|\.|\,|\!|\?|\&nbsp\;|\&hellip\;|$)/uie',
				'replacement' 	=> '$m[1] . $this->tag($m[2], "span", ["class"=>"nowrap")] . $m[4]',
			],
		'hyphen_nowrap' => [
				'description'	=> 'Отмена переноса слова с дефисом',
				'disabled'	 => true,
				'cycled'		=> true,
				'pattern' 		=> '/(\&nbsp\;|\s|\>|^)([a-zа-яё]+)((\-([a-zа-яё]+)){1,2})(\s|\.|\,|\!|\?|\&nbsp\;|\&hellip\;|$)/uie',
				'replacement' 	=> '$m[1] . $this->tag($m[2].$m[3], "span", ["class"=>"nowrap")] . $m[6]'
			],
		];

	/**
	 * Объединение IP-адрессов в неразрывные конструкции (IPv4 only)
	 *
	 * @param unknown_type $triads
	 * @return unknown
	 */
	protected function nowrap_ip_address($triads)
	{
		$triad = explode('.', $triads);
		$addTag = true;

		foreach ($triad as $value) {
			$value = (int) $value;
			if ($value > 255) {
				$addTag = false;
				break;
			}
		}

		if (true === $addTag) {
			$triads = $this->tag($triads, 'span', ['class' => "nowrap"]);
		}

		return $triads;
	}
}