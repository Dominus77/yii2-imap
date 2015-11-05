<?php

namespace camohob\imap;

use yii\base\Object;
use yii\base\Component;

/**
 * Description of ImapAttachment
 *
 * @author camohob
 * 
 * @property string $data
 */
class ImapAttachment extends Component {

    public $type;
    public $encoding;
    public $mimetype;
    public $filename;
    public $title;
    public $size;
    protected $message;
    protected $part;
    protected $_data;

    public function __construct(ImapMessage &$message, $part) {
        $this->message = $message;
        $this->part = $part;
    }

    function getData() {
        if ($this->_data === null) {
            $this->_data = $this->message->getData($this->part, $this->encoding);
        }
        return $this->_data;
    }

}
