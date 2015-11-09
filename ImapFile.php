<?php

namespace camohob\imap;

/**
 * Attachment
 *
 * @author camohob
 * 
 * @property-read string $mimetype
 * @property-read string $filename
 * @property-read integer $size
 * @property-read string $title
 * @property-read boolean $isAttachment
 */
class ImapFile extends MessagePart {

    public function getMimetype() {
        return strtolower(static::$types[$this->getType()] . '/' . $this->getSubtype());
    }
    
    public function getFilename(){
        $filename = $this->getParameters('name');
        $filetitle = $this->getTitle();
        return $filename === null ? ($filetitle === null ? time() : $filetitle) . $this->getSubtype() : $filename;
    }
    
    public function getSize() {
        return $this->getBytes();
    }
    
    public function getTitle() {
        return $this->getDparameters('filename');
    }
    
    public function getIsAttachment() {
        return $this->getDisposition() === 'attachment';
    }

}
