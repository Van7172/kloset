<?php

namespace Develoweb\App\Model;

class Msgbox
{

	private $_text, $_type;

	public function setMsgbox ($text, $type)
	{
		$this->_text = $text;
		$this->_type = $type;
	}

	public function getMsgbox ()
	{
		switch ($this->_type) {
			case 1:
				$msg = "<div class=\"notification alert alert-danger\" role=\"alert\">{$this->_text}</div>";
			break;
			case 2:
				$msg = "<div class=\"notification alert alert-success\" role=\"alert\"><i class=\"fas fa-check\"></i> {$this->_text}</div>";
			break;
		}
		$this->clearMsgbox();
		return $msg ?? '';
	}

	public function clearMsgbox ()
	{
		$this->_text = '';
		$this->_type = '';
	}

}