<?php

namespace Develoweb\App\Model;

class Msgbox
{

	private $_text, $_type;

	public function __serialize(): array
	{
		return ['text' => $this->_text ?? '', 'type' => $this->_type ?? ''];
	}

	public function __unserialize(array $data): void
	{
		// Las sesiones anteriores usaban propiedades públicas mensaje/tipo.
		$fields = [];
		foreach ($data as $key => $value) {
			$separator = strrpos($key, "\0");
			$fields[$separator === false ? $key : substr($key, $separator + 1)] = $value;
		}
		$this->_text = $fields['text'] ?? $fields['_text'] ?? $fields['mensaje'] ?? '';
		$this->_type = $fields['type'] ?? $fields['_type'] ?? $fields['tipo'] ?? '';
	}

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
