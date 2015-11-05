<?php

namespace camohob\imap;

use yii\base\Component;
use yii\web\HttpException;

/**
 * Example:
 * 
 * <code>
 * $imap = new ImapAgent;
 * $imap->type = 'imap/ssl/novalidate-cert';
 * $imap->server = 'imap.mail.ru';
 * $imap->port = 993;
 * $imap->user = 'mailbox@mail.ru';
 * $imap->password = 'password';
 *         
 * foreach ($imap->messages as $message) {
 *      $message->delete();
 * }
 * 
 * $imap->close();
 * 
 * </code>
 * 
 * @author camohob <v.samonov@mail.ru>
 * 
 * @property-read ImapMessage[] $messages Return array of ImapMessge class
 * if you want to read other messages - use <code>getMessages($search);</code>
 * @property-read integer $count
 * @property-read resource $stream      Mailbox stream
 * @property string $user               Username
 * @property string $password           Password
 * @property string $server             Host connect to server<br>example: mail.example.com
 * @property integer $port              Port connect to server<br>example: 110, 993, 995
 * @property string $type               Type connect to server<br>example: pop3, pop3/ssl, imap/ssl/novalidate-cert
 */
class ImapAgent extends Component {

    protected $_server;
    protected $_port = 110;
    protected $_type = 'pop3';
    protected $_options = 0;
    //
    public $_user;
    public $_password;

    /**
     * Ping everytime when getting stream
     * @var boolean 
     */
    public $pingStream = false;
    //
    private $_count;

    /*
     * @var $_messages[] ImapMessage;
     */
    private $_messages;

    /**
     * mailbox stream
     * @var resource
     */
    protected $_stream;
    private $_init = false;

    public function init() {
        if (!extension_loaded("imap"))
            throw new HttpException(500, 'Could not load extension "imap". Please install extension.');
        $this->_init = true;
    }

    public function getStream() {
        if ($this->_stream !== null && (!is_resource($this->_stream) || ($this->pingStream && !imap_ping($this->_stream)))) {
            $this->disconnect();
        }
        if ($this->_stream === null) {
            $this->connect();
        }

        return $this->_stream;
    }

    protected function connect() {
        if (!$this->_init) {
            $this->init();
        }
        $this->_stream = @imap_open('{' . $this->getServer() . ':' . $this->getPort() . '/' . $this->getType() . '}INBOX', $this->getUser(), $this->getPassword(), $this->getOptions());
        if (!$this->_stream) {
            if (imap_last_error()) {
                throw new HttpException(500, 'imap_last_error() : ' . imap_last_error());
            } else {
                throw new HttpException(500, 'Couldn\'t open stream  ' . $this->getServer() . ':' . $this->getPort() . '.');
            }
        }
    }

    protected function disconnect() {
        if ($this->_stream && is_resource($this->_stream)) {
            imap_close($this->_stream, CL_EXPUNGE);
        }
        $this->_stream = null;
        $this->_messages = null;
        $this->_count = null;
    }

    public function close() {
        $this->disconnect();
    }

    public function getCount() {
        if ($this->_count === null) {
            $this->_count = imap_num_msg($this->getStream());
        }
        return $this->_count;
    }

    /**
     * 
     * @param string $search String type of message e.g. "ALL", "UNSEEN" or "UNSEEN DELETED"
     * default is "UNSEEN DELETED"
     * read <url>http://php.net/manual/ru/function.imap-search.php</url> for more details
     * @return ImapMessage[] Array of ImapMessage class
     */
    public function getMessages($search = 'UNSEEN UNDELETED') {
        if ($this->_messages === null) {
            $this->_messages = [];
            foreach ($this->getMsgList($search) as $msg) {
                $this->_messages[] = new ImapMessage($this, $msg);
            }
        }
        return $this->_messages;
    }

    /**
     * 
     * @param integer $msgId Message ID
     * @return ImapMessage ImapMessage object
     */
    public function getMessage($msgId = null) {
        if ($msgId === null) {
            $list = $this->getMsgList();
            if (empty($list)) {
                $list = $this->getMsgList('ALL');
            }
            if (!empty($list) && is_array($list) && isset($list[0])) {
                $msgId = $list[0];
            }
        }
        if ($msgId !== null || $msgId !== false) {
            return new ImapMessage($this, $msgId);
        }
        return null;
    }

    /**
     * Get messages id
     * @return array
     */
    protected function getMsgList($search = 'UNSEEN UNDELETED') {
        $list = imap_search($this->getStream(), $search);
        return !$list ? [] : $list;
    }

    public function getServer() {
        return $this->_server;
    }

    public function setServer($val) {
        $this->_server = $val;
        $this->disconnect();
    }

    public function getPort() {
        return $this->_port;
    }

    public function setPort($val) {
        $this->_port = $val;
        $this->disconnect();
    }

    public function getType() {
        return $this->_type;
    }

    public function setType($val) {
        $this->_type = $val;
        $this->disconnect();
    }

    public function getOptions() {
        return $this->_options;
    }

    public function setOptions($val) {
        $this->_options = $val;
        $this->disconnect();
    }

    public function getUser() {
        return $this->_user;
    }

    public function setUser($val) {
        $this->_user = $val;
        $this->disconnect();
    }

    public function getPassword() {
        return $this->_password;
    }

    public function setPassword($val) {
        $this->_password = $val;
        $this->disconnect();
    }

}
