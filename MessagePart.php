<?php

/*
 * 
 */

namespace camohob\imap;

/**
 * Part of message
 * 
 * @property-read string $partno        This part number
 * @property-read integer $type	Primary body type
 * @property-read string $encoding	Body transfer encoding
 * @property-read string $subtype	MIME subtype
 * @property-read string $description	Content description string
 * @property-read string $id	Identification string
 * @property-read integer $lines	Number of lines
 * @property-read integer $bytes	Number of bytes
 * @property-read string $disposition	Disposition string
 * @property-read \stdClass $dparameters	Corresponding to the parameters on the Content-disposition MIME header.
 * @property-read \stdClass $parameters	Properties.
 * @property-read MessagePart[] $parts	An array of objects identical in structure to the top-level object, each of which corresponds to a MIME body part.
 *
 * @author camohob <v.samonov@mail.ru>
 */
class MessagePart extends Object {

    const DEFAULT_IMAP_CHARSET = 'utf-8';
    //
    const TYPE_TEXT = 0;
    const TYPE_MULTIPART = 1;
    const TYPE_MESSAGE = 2;
    const TYPE_APPLICATION = 3;
    const TYPE_AUDIO = 4;
    const TYPE_IMAGE = 5;
    const TYPE_VIDEO = 6;
    const TYPE_MODEL = 7;
    const TYPE_OTHER = 8;

    public static $types = [
        self::TYPE_TEXT => 'text',
        self::TYPE_MULTIPART => 'multipart',
        self::TYPE_MESSAGE => 'message',
        self::TYPE_APPLICATION => 'application',
        self::TYPE_AUDIO => 'audio',
        self::TYPE_IMAGE => 'image',
        self::TYPE_VIDEO => 'video',
        self::TYPE_MODEL => 'model',
        self::TYPE_OTHER => 'other',
    ];

    /**
     * Link to ImapMessage Object
     * @var ImapMessage
     */
    protected $_message;
    protected $_partno;
    protected $_structure;
    protected $_parts;
    protected $_parameters;
    protected $_dparameters;

    public function __construct($message, $part = null, $partno = 0, $config = []) {
        parent::__construct($config);
        $this->_message = $message;
        $this->_partno = $partno;
        if ($part !== null) {
            $this->_structure = $part;
//        } else {
//            $this->_structure = imap_fetchstructure($this->_message->imap->getStream(), $this->_message->getUid(), FT_UID);
//            xdebug_var_dump($this->_structure);
        }
    }

    protected function getStructure() {
        if ($this->_structure === null) {
            $this->_structure = imap_fetchstructure($this->_message->getImap()->getStream(), $this->_message->getUid(), FT_UID);
        }
        return $this->_structure;
    }

    public function getPartno() {
        return $this->_partno;
    }

    /**
     * 
     * @return MessagePart[]
     */
    public function getParts() {
        if ($this->_parts === null) {
            $this->_parts = [];
            $struct = $this->getStructure();
            if (isset($struct->parts) && is_array($struct->parts)) {
                foreach ($struct->parts as $partId => $nextPart) {
                    if ($this->getProperty('type', $nextPart) === self::TYPE_MULTIPART || ($this->getProperty('type', $nextPart) === self::TYPE_TEXT && $this->getProperty('disposition', $nextPart) !== 'attachment')) {
                        $this->_parts[] = new MessagePart($this->_message, $nextPart, (!$this->_partno ? '' : $this->_partno . '.') . ($partId + 1));
                    } else {
                        $this->_parts[] = new ImapFile($this->_message, $nextPart, (!$this->_partno ? '' : $this->_partno . '.') . ($partId + 1));
                    }
                }
                $this->_structure->parts = null;
            }
        }
        return $this->_parts;
    }

    /**
     * Get part data
     * @return string
     */
    public function getData() {
        $encoding = $this->getEncoding();
        $charset = $this->getParameters('charset');
        if (!$this->getPartno()) {
            $body = imap_body($this->_message->getImap()->getStream(), $this->_message->getUid(), FT_UID);
        } else {
            $body = imap_fetchbody($this->_message->getImap()->getStream(), $this->_message->getUid(), $this->getPartno(), FT_UID);
        }
        return $this->decodeData($body, $encoding, $charset ? $charset : self::DEFAULT_IMAP_CHARSET);
    }

    protected function decodeData($data, $encoding = 0, $data_charset = 'utf-8') {
        switch ($encoding) {
            case ENC7BIT:
            case ENC8BIT:
                $data = imap_utf8($data);
                break;
            case ENCBINARY:
                $data = imap_binary($data);
                break;
            case ENCBASE64:
                $data = base64_decode($data);
                break;
            case ENCQUOTEDPRINTABLE:
                $data = quoted_printable_decode($data);
                break;
            default:
                break;
        }
        return ($data_charset === $this->_message->getImap()->serverCharset) ? $data : mb_convert_encoding($data, $this->_message->getImap()->serverCharset, $data_charset);
    }

    protected function getProperty($method, $object = null) {
        if ($object === null) {
            $object = $this->getStructure();
        }
        $method = explode('::', $method);
        if (is_array($method)) {
            $method = array_pop($method);
        } else {
            return null;
        }
        $prop = strtolower(str_replace('get', '', $method));
        $ifprop = 'if' . $prop;
        if ((!isset($object->$ifprop) || $object->$ifprop) && isset($object->$prop)) {
            return $object->$prop;
        }
        return null;
    }

    public function getId() {
        return $this->getProperty(__METHOD__);
    }

    public function getType() {
        return $this->getProperty(__METHOD__);
    }

    public function getEncoding() {
        return $this->getProperty(__METHOD__);
    }

    public function getSubtype() {
        return $this->getProperty(__METHOD__);
    }

    public function getDescription() {
        return $this->getProperty(__METHOD__);
    }

    public function getLines() {
        return $this->getProperty(__METHOD__);
    }

    public function getBytes() {
        return $this->getProperty(__METHOD__);
    }

    public function getDisposition() {
        return $this->getProperty(__METHOD__);
    }

    protected function parseParams($params) {
        $arr = [];
        if (!empty($params)) {
            foreach ($params as $param) {
                if (!isset($param->attribute)) {
                    continue;
                }
                $arr[$param->attribute] = isset($param->value) ? $this->decodeMime($param->value) : null;
            }
        }
        return $arr;
    }

    protected function decodeMime($text) {
        $list = imap_mime_header_decode($text);
        $result = '';
        foreach ($list as $data) {
            $charset = isset($data->charset) ? $data->charset : 'default';
            $result.= mb_convert_encoding($data->text, $this->_message->getImap()->serverCharset, $charset == 'default' ? self::DEFAULT_IMAP_CHARSET : $charset);
        }
        return $result;
    }

    public function getParameters($param = null) {
        if (!isset($this->_parameters)) {
            $val = $this->getProperty(__METHOD__);
            $this->_parameters = &$val;
        }
        if ($param !== null) {
            if (isset($this->_parameters[$param])) {
                return $this->_parameters[$param];
            }
            return null;
        }
        return $this->_parameters;
    }

    public function getDparameters($param = null) {
        if (!isset($this->_dparameters)) {
            $val = $this->parseParams($this->getProperty(__METHOD__));
            $this->_dparameters = &$val;
        }
        if ($param !== null) {
            if (isset($this->_dparameters[$param])) {
                return $this->_dparameters[$param];
            }
            return null;
        }
        return $this->_dparameters;
    }

}
