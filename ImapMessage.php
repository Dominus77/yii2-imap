<?php

/*
 * 
 * 
 * 
 */

namespace camohob\imap;

use yii\base\Component;

/**
 * Description of ImapMessage
 *
 * @author camohob <v.samonov@mail.ru>
 * 
 * @property string $subject Subject
 * @property string $body Return body of message in "html" format. If you want to get result in other format - use <code>getBody($type);</code>
 * @property string $from Return "from" field of message, consist of address and name of sender.
 * @property string $to Return "to" field of message
 * @property integer $type Return type of message
 * @property-read ImapAttachment[] $attachments Attachments of message.
 * @property-read ImapAttachment[] $content Message content as ImapAttachment
 * @property-read Object $headerInfo
 */
class ImapMessage extends Component {

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
    protected $imap;
    protected $id;
    protected $_headerInfo;
    protected $_structure;
    protected $_subject;
    protected $_body;
    protected $_attachments = [];
    protected $_content = [];

    public function init() {
        parent::init();
    }

    public function __construct(ImapAgent &$imap, $id, $config = []) {
        parent::__construct($config);

        $this->imap = $imap;
        $this->id = $id;
    }

    protected function getStructure() {
        if ($this->_structure === null) {
            $this->_structure = imap_fetchstructure($this->imap->getStream(), $this->id);
        }
        return $this->_structure;
    }

    public function getHeaderInfo() {
        if ($this->_headerInfo === null) {
            $this->_headerInfo = imap_headerinfo($this->imap->getStream(), $this->id);
        }
        return $this->_headerInfo;
    }

    protected function fetchParams($params) {
        $arr = [];
        foreach ($params as $param) {
            $arr[$param->attribute] = $this->decodeMime($param->value);
        }
        return $arr;
    }

    protected function decodeMime($text) {
        $list = imap_mime_header_decode($text);
        $result = '';
        foreach ($list as $data) {
            $result.= mb_convert_encoding($data->text, 'utf-8', $data->charset == 'default' ? 'iso-8859-1' : $data->charset);
        }
        return $result;
    }

    protected function decodeData($data, $encoding = 0, $charset = 'utf-8') {
        switch ($encoding) {
            case 0:
            case 1:
                $data = imap_utf8($data);
                break;
            case 2:
                $data = imap_binary($data);
                break;
            case 3:
                $data = base64_decode($data);
                break;
            case 4:
                $data = quoted_printable_decode($data);
                break;
            default:
                break;
        }
        return $charset === 'utf-8' ? $data : mb_convert_encoding($data, 'utf-8', $charset);
    }

    /**
     * 
     * @param stdClass $part
     * @param array $params
     * @param array $dparams
     * @return ImapAttachment
     */
    protected function createAttachment($partno, $part, $params = [], $dparams = []) {
        $filetitle = isset($dparams['filename']) ? $dparams['filename'] : '';
        $filename = isset($params['name']) ? $params['name'] : ((!empty($filetitle) ? $filetitle : time()) . isset($part->subtype) ? $part->subtype : '' );

        $attachment = new ImapAttachment($this, $partno);
        $attachment->type = $part->type;
        $attachment->encoding = isset($part->encoding) ? $part->encoding : 0;
        $attachment->mimetype = strtolower(static::$types[$part->type] . '/' . (isset($part->subtype) ? $part->subtype : ''));
        $attachment->title = $filetitle;
        $attachment->filename = $filename;
        $attachment->size = isset($part->bytes) ? $part->bytes : null;

        return $attachment;
    }

    protected function fetchBody($part = null, $partno = 0) {
        if ($this->_body !== null) {
            return [];
        }
        if ($part === null) {
            $part = $this->getStructure();
        }
        $body = [];
        $params = $this->fetchParams(isset($part->parameters) ? $part->parameters : []);
        $dparams = $this->fetchParams(isset($part->dparameters) ? $part->dparameters : []);
        if ($part->ifdisposition && strtolower($part->disposition) == 'attachment') {
            $this->addAttachment($this->createAttachment($partno, $part, $params, $dparams));
        } else {
            switch ($part->type) {
                case self::TYPE_TEXT:
                    $data = !$partno ? imap_body($this->imap->getStream(), $this->id) : imap_fetchbody($this->imap->getStream(), $this->id, $partno);
                    $body[static::$types[self::TYPE_TEXT]][strtolower($part->subtype)][] = $this->decodeData($data, isset($part->encoding) ? $part->encoding : 0, isset($params['charset']) ? $params['charset'] : 'utf-8');
                    break;
                case self::TYPE_MULTIPART:
                    foreach ($part->parts as $part_id => $next_part) {
                        $parts = $this->fetchBody($next_part, (!$partno ? '' : $partno . '.') . ($part_id + 1));
//					if (strtolower($part->subtype) == 'alternative') {
//						break;
//					}
                        $body = array_merge($body, $parts);
                    }
                    break;
                case self::TYPE_MESSAGE:
                //original message
//                    break;
                case self::TYPE_APPLICATION:
//				break;
                case self::TYPE_AUDIO:
//				break;
                case self::TYPE_IMAGE:
//				break;
                case self::TYPE_VIDEO:
//				break;
                case self::TYPE_MODEL:
//                    break;
                case self::TYPE_OTHER:
                default :
                    $this->addContent($this->createAttachment($partno, $part, $params, $dparams));
                    break;
            }
        }
        if (!$partno) {
            $this->_body = $body;
            return;
        }
        return $body;
    }

    public function getSubject() {
        if ($this->_subject === null) {
            $this->_subject = $this->decodeMime(isset($this->getHeaderInfo()->subject) ? $this->getHeaderInfo()->subject : '');
        }
        return $this->_subject;
    }

    public function getData($part, $encoding) {
        return $this->decodeData(imap_fetchbody($this->imap->getStream(), $this->id, $part), $encoding);
    }

    /**
     * 
     * @param string $type May be "html" or "plain", if required format not found - function returns plain text
     * @return string
     */
    public function getBody($type = 'html') {
        $this->fetchBody();
        $result = null;
        $alter = null;
        if (isset($this->_body[static::$types[self::TYPE_TEXT]])) {
            foreach ($this->_body[static::$types[self::TYPE_TEXT]] as $subtype => $subtypes) {
                foreach ($subtypes as $text) {
//					xdebug_var_dump($subtype);
                    if ($subtype == $type) {
                        $result.= $text;
                    } else {
                        $alter.= $text;
                    }
                }
            }
        }
        return empty($result) ? $alter : $result;
    }

    public function getFrom() {
        return $this->decodeMime($this->getHeaderInfo()->fromaddress);
    }

    public function getTo() {
        return $this->decodeMime($this->getHeaderInfo()->toadress);
    }

    public function getType() {
        return $this->getStructure()->type;
    }

    public function getMime() {
        return $this->getStructure()->subtype;
    }

    protected function addContent($content) {
        $this->_content[] = $content;
    }
    
    /**
     * 
     * @return ImapAttachment[]
     */
    public function getContent() {
        return $this->_content;
    }

    protected function addAttachment($attach) {
        $this->_attachments[] = $attach;
    }

    function getAttachments() {
        return $this->_attachments;
    }

    public function delete() {
        imap_delete($this->imap->getStream(), $this->id);
    }

}
