<?php
/*
Evgeny Muravjev Typograph, http://mdash.ru
class EMT_Tret_Text
php8 version
AT / atis.pro
28.12.23
*/

namespace EMT;

class EMT_Tret_Text extends EMT_Tret
{
	public $classes = [
			'nowrap'		 => 'word-spacing:nowrap;',
		];
	/**
	 * Базовые параметры тофа
	 *
	 * @var array
	 */
	public $title = "Текст и абзацы";
	public $rules = [
		'auto_links' => [
				'description'	=> 'Выделение ссылок из текста',
				'pattern' 		=> '/(\s|^)(http|ftp|mailto|https)(:\/\/)([^\s\,\!\<]{4,})(\s|\.|\,|\!|\?|\<|$)/ieu', 
				'replacement' 	=> '$m[1] . $this->tag((substr($m[4],-1)=="."?substr($m[4],0,-1):$m[4]], "a", ["href" => $m[2].$m[3].(substr($m[4],-1)=="."?substr($m[4],0,-1):$m[4]))) . (substr($m[4],-1)=="."?".":""] .$m[5]'
			],
		'email' => [
				'description'	=> 'Выделение эл. почты из текста',
				'pattern' 		=> '/(\s|^|\&nbsp\;|\()([a-z0-9\-\_\.]{2,})\@([a-z0-9\-\.]{2,})\.([a-z]{2,6})(\)|\s|\.|\,|\!|\?|$|\<)/e',
				'replacement' 	=> '$m[1] . $this->tag($m[2]."@".$m[3].".".$m[4], "a", ["href" => "mailto:".$m[2]."@".$m[3].".".$m[4]]) . $m[5]'
			],
		'no_repeat_words' => [
				'description'	=> 'Удаление повторяющихся слов',
				'disabled'	 => true,
				'pattern' 		=> [
					'/([а-яё]{3,})( |\t|\&nbsp\;)\1/iu',
					'/(\s|\&nbsp\;|^|\.|\!|\?)(([А-ЯЁ])([а-яё]{2,}))( |\t|\&nbsp\;)(([а-яё])\4)/eu',
					],
				'replacement' 	=> [
					'\1',
					'$m[1].($m[7] === EMT\EMT_Lib::strtolower($m[3]) ? $m[2] : $m[2].$m[5].$m[6] )',
					]
			],
		'paragraphs' => [
				'description'	=> 'Простановка параграфов',
				'function'	=> 'build_paragraphs'
			],
		'breakline' => [
				'description'	=> 'Простановка переносов строк',
				'function'	=> 'build_brs'
			],

		];
	/**
	 * Расстановка защищенных тегов параграфа (<p>...</p>) и переноса строки
	 *
	 * @return  void
	 */
	protected function do_paragraphs($text) {
		$text = str_replace("\r\n","\n",$text);
		$text = str_replace("\r","\n",$text);
		$text = '<' . self::BASE64_PARAGRAPH_TAG . '>' . trim($text) . '</' . self::BASE64_PARAGRAPH_TAG . '>';
		//$text = $this->preg_replace_e('/([\040\t]+)?(\n|\r){2,}/e', '"</" . self::BASE64_PARAGRAPH_TAG . "><" .self::BASE64_PARAGRAPH_TAG . ">"', $text);
		//$text = $this->preg_replace_e('/([\040\t]+)?(\n){2,}/e', '"</" . self::BASE64_PARAGRAPH_TAG . "><" .self::BASE64_PARAGRAPH_TAG . ">"', $text);
		$text = $this->preg_replace_e('/([\040\t]+)?(\n)+([\040\t]*)(\n)+/e', '$m[1]."</" . self::BASE64_PARAGRAPH_TAG . ">" . EMT\EMT_Lib::iblock($m[2].$m[3]) . "<" .self::BASE64_PARAGRAPH_TAG . ">"', $text);
		//$text = $this->preg_replace_e('/([\040\t]+)?(\n)+([\040\t]*)(\n)+/e', '"</" . self::BASE64_PARAGRAPH_TAG . ">"."<" .self::BASE64_PARAGRAPH_TAG . ">"', $text);
		//может от открвающего до закрывающего ?!
		$text = preg_replace('/\<' . self::BASE64_PARAGRAPH_TAG . '\>('.EMT_Lib::INTERNAL_BLOCK_OPEN.'[a-zA-Z0-9\/=]+?'.EMT_Lib::INTERNAL_BLOCK_CLOSE.')?\<\/' . self::BASE64_PARAGRAPH_TAG . '\>/s', "", $text);
		return $text;
	}

	/**
	 * Расстановка защищенных тегов параграфа (<p>...</p>) и переноса строки
	 *
	 * @return  void
	 */
	protected function build_paragraphs()
	{
		$r = mb_strpos($this->_text, '<' . self::BASE64_PARAGRAPH_TAG . '>' );
		$p = EMT_Lib::rstrpos($this->_text, '</' . self::BASE64_PARAGRAPH_TAG . '>' );
		if(($r!== false) && ($p !== false)) {

			$beg = mb_substr($this->_text,0,$r);
			$end = mb_substr($this->_text,$p+mb_strlen('</' . self::BASE64_PARAGRAPH_TAG . '>'));
			$this->_text = 
							(trim($beg) ? $this->do_paragraphs($beg). "\n":"") .'<' . self::BASE64_PARAGRAPH_TAG . '>'.
							mb_substr($this->_text,$r + mb_strlen('<' . self::BASE64_PARAGRAPH_TAG . '>'),$p -($r + mb_strlen('<' . self::BASE64_PARAGRAPH_TAG . '>')) ).'</' . self::BASE64_PARAGRAPH_TAG . '>'.
							(trim($end) ? "\n".$this->do_paragraphs($end) :"") ;
		} else {
			$this->_text = $this->do_paragraphs($this->_text);
		}
	}
	/**
	 * Расстановка защищенных тегов параграфа (<p>...</p>) и переноса строки
	 *
	 * @return  void
	 */
	protected function build_brs()
	{
		$this->_text = $this->preg_replace_e('/(\<\/' . self::BASE64_PARAGRAPH_TAG . '\>)([\r\n \t]+)(\<' . self::BASE64_PARAGRAPH_TAG . '\>)/mse', '$m[1].EMT\EMT_Lib::iblock($m[2]).$m[3]', $this->_text);

		if (!preg_match('/\<' . self::BASE64_BREAKLINE_TAG . '\>/', $this->_text)) {
			$this->_text = str_replace("\r\n","\n",$this->_text);
			$this->_text = str_replace("\r","\n",$this->_text);
			//$this->_text = $this->preg_replace_e('/(\n|\r)/e', '"<" . self::BASE64_BREAKLINE_TAG . ">"', $this->_text);
			$this->_text = $this->preg_replace_e('/(\n)/e', '"<" . self::BASE64_BREAKLINE_TAG . ">\n"', $this->_text);
		}
	}
}