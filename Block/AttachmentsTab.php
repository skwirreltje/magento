<?php
namespace Skwirrel\Pim\Block;

class AttachmentsTab extends \Magento\Catalog\Block\Product\View
{

    public function getFiles($product)
    {
        $attachments = $product->getData('attachments');

        if (!is_array($attachments)) {
            $attachments = json_decode($attachments, true);
        }
        else{
        }

        $files = [];

        if($attachments){

            foreach($attachments as $attachment){

                $fileType = $attachment['file_type'];
                $parts = explode('/',$fileType);
                $attachment['file_type'] = isset($parts[1]) ? $parts[1] : $fileType;
                $files[] = $attachment;

            }
        }


        return $files;

    }
}