<?php

namespace Xendit\M2Invoice\Helper;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Xendit\M2Invoice\Helper\Data as XenditHelperData;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Qrcode
 * @package Xendit\M2Invoice\Helper
 */
class Qrcode extends AbstractHelper
{
    const IMAGE_EXTENSION   = '.svg';
    const QRCODE_IMAGE_PATH = 'payment/qrcode';

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var File
     */
    protected $file;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Data
     */
    protected $xenditHelperData;

    /**
     * Qrcode constructor.
     * @param Context $context
     * @param Filesystem $filesystem
     * @param File $file
     * @param StoreManagerInterface $storeManager
     * @param Data $xenditHelperData
     */
    public function __construct(
        Context $context,
        Filesystem $filesystem,
        File $file,
        StoreManagerInterface $storeManager,
        XenditHelperData $xenditHelperData
    ) {
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
        $this->file = $file;
        $this->xenditHelperData = $xenditHelperData;
        parent::__construct($context);
    }

    /**
     * Generate QR code using qr string
     * @param $qrString
     * @param $externalId
     * @return string
     * @throws LocalizedException
     */
    public function generateQrcode($qrString, $externalId)
    {
        // get qrcode image save path
        $qrcodeImagePath = $this->getQrcodeImagePath($externalId);
        $qrcodeImageUrl = $this->getQrcodeImageUrl($externalId);
        // generate QrImage
        $renderer = new ImageRenderer(
            new RendererStyle($this->xenditHelperData->getQrCodesImageWidth()),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $writer->writeFile($qrString, $qrcodeImagePath);
        return $qrcodeImageUrl;
    }

    /**
     * @return string
     */
    public function getMediaPath()
    {
        return $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
    }

    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getMediaUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
    }

    public function getQrcodeImageUrl($externalId)
    {
        return $this->getMediaUrl() . self::QRCODE_IMAGE_PATH . '/' . $externalId . self::IMAGE_EXTENSION;
    }

    /**
     * Create "qrcode" dir and
     * return image path
     * @param $orderId
     * @return string
     * @throws LocalizedException
     */
    public function getQrcodeImagePath($externalId)
    {
        $filePath = $this->getMediaPath() . self::QRCODE_IMAGE_PATH;
        $qrcodeImagePath = $filePath . '/' . $externalId . self::IMAGE_EXTENSION;

        if (!file_exists($filePath)) {
            try {
                $this->file->mkdir($filePath);
            } catch (\Exception $e) {
                throw new LocalizedException(
                    __('Can\'t create directory "%1"', $filePath)
                );
            }
        }
        return $qrcodeImagePath;
    }
}
