<?php
namespace lib\model\types;
include_once(LIBPATH."/model/types/File.php");
 class  ImageTypeException extends FileTypeException{ 
      const ERR_NOT_AN_IMAGE=120;
      const ERR_TOO_SMALL=121;
      const ERR_TOO_WIDE=122;
      const ERR_TOO_SHORT=123;
      const ERR_TOO_TALL=124; 
  }

class ImageType extends FileType
{
    function __construct($def,$neutralValue=null)
    {
        if(!$def["EXTENSIONS"])
           $def["EXTENSIONS"]=array("jpg","gif","png");
        parent::__construct($def,$neutralValue);
    }
    function validate($value)
    {
        parent::validate($value);
        if(!$this->hasOwnValue())
            return;

        // Se realizan las comprobaciones
        $size=getImageSize($value);
        $minWidth=isset($this->definition["MINWIDTH"]);
        $minHeight=isset($this->definition["MINHEIGHT"]);
        $maxWidth=isset($this->definition["MAXWIDTH"]);
        $maxHeight=isset($this->definition["MAXHEIGHT"]);

        if($minWidth && $size[0] < $this->definition["MINWIDTH"])
            throw new ImageTypeException(ImageTypeException::ERR_TOO_SMALL,array("min"=>$this->definition["MINWIDTH"],"current"=>$size[0]));
        if($maxWidth && $size[0] > $this->definition["MAXWIDTH"])
            throw new ImageTypeException(ImageTypeException::ERR_TOO_WIDE,array("max"=>$this->definition["MAXWIDTH"],"current"=>$size[0]));
        if($minHeight && $size[1] < $this->definition["MINHEIGHT"])
            throw new ImageTypeException(ImageTypeException::ERR_TOO_SHORT,array("min"=>$this->definition["MINHEIGHT"],"current"=>$size[1]));
        if($maxHeight && $size[1] > $this->definition["MAXHEIGHT"])
            throw new ImageTypeException(ImageTypeException::ERR_TOO_TALL,array("max"=>$this->definition["MAXHEIGHT"],"current"=>$size[1]));
    }

    function save()
    {
        if(!$this->hasOwnValue())
            return;
        // Se almacena el estado actual de dirty, ya que se necesita despues, y el save() de file lo va a poner a false.
        $savedDirty=$this->dirty;
        parent::save();
        // Se restaura el valor anterior de dirty, para crear thumbnails y watermarks.
        $this->dirty=$savedDirty;
        if(isset($this->definition["THUMBNAIL"]))        
          $this->makeThumbNail($this->definition["THUMBNAIL"]);
        if(isset($this->definition["WATERMARK"]))
          $this->addWatermark($this->definition["WATERMARK"]);        
        // Finalmente, se pone dirty a false.
        $this->dirty=false;
    }

    public function makeThumbNail($def)
    {
        if(!$this->hasOwnValue())
            return;
        if(!$this->dirty)
            return;

        $srcImage=$this->getFullFilePath();
        $sizes=getimagesize($srcImage);
        if(!$sizes)
            return;

        $width=$sizes[0];
        $height=$sizes[1];
        $mode=0; // 0: portrait, 1:landscape
        if($width>$height)
            $mode=1;

        $dwidth=isset($def["WIDTH"])?$def["WIDTH"]:100;
        $dheight=isset($def["HEIGHT"])?$def["HEIGHT"]:100;       

        if(!isset($def["KEEPASPECT"]) || $def["KEEPASPECT"])
        {
             if($dwidth)
                 $diffwidth=abs($dwidth-$width);

             if($dheight)
                 $diffheight=abs($dheight-$height);

             if($diffheight && ($diffheight < $diffwidth || !$dwidth))
                     $dwidth=$width*($dheight/$height);

             if($diffwidth && ($diffwidth < $diffheight || !$dheight))
                    $dheight=($dwidth*$height)/$width;
        }
        // apertura de imagen..El destino va a ser siempre jpg.Hay que saber que tipo de imagen
        // exacta es el origen.
        $type=image_type_to_extension($sizes[2],false);
        $funcName="imagecreatefrom".$type;
        $img=$funcName($srcImage);
        if(!$img)
            return;
        // se crea la imagen destino.
        $destImg=imagecreatetruecolor(intval($dwidth),intval($dheight));
        imagecopyresampled($destImg,$img,0,0,0,0,$dwidth,$dheight,$width,$height);
        $fileName=basename($srcImage);
        $suffix=$def["PREFIX"];
        if(!$suffix)
            $suffix="th_";
        $quality=$def["QUALITY"];
        if(!$quality)
            $quality=85;
        imagejpeg($destImg,dirname($srcImage)."/".$suffix.$fileName,$quality);
    }

    public function addWatermark($def)
    {
        if(!$this->hasOwnValue())
            return;
        $dstFile=$this->value;
        $wtSize=getimagesize($def["FILE"]); // Fichero del watermark.
        $srcSize=getimagesize($dstFile);
        $quality=85;
        switch($def["POSITION"])
        {
            case "NW":{
                $dstx=$def["OFFSETX"];
                $dsty=$def["OFFSETY"];
            }break;
        case "NE":
            {
                $dstx=$srcSize[0]-$wtSize[0]-$def["OFFSETX"];
                $dsty=$def["OFFSETY"];
            }break;
        case "SE":
            {
                $dstx=$srcSize[0]-$wtSize[0]-$def["OFFSETX"];
                $dsty=$srcSize[1]-$wtSize[1]-$def["OFFSETY"];
            }break;
        case "SW":
            {
                $dstx=$def["OFFSETX"];
                $dsty=$srcSize[1]-$wtSize[1]-$def["OFFSETY"];
            }break;
        case "CENTER":
            {
                $dstx=intval(($srcSize[0]-$wtSize[0])/2);
                $dsty=intval(($srcSize[1]-$wtSize[1])/2);
            }break;
        }
        $wttype=image_type_to_extension($wtSize[2],false);
        $funcName="imagecreatefrom".$wttype;
        $wtimg=$funcName($def["FILE"]);
        
        $srctype=image_type_to_extension($srcSize[2],false);
        $funcName="imagecreatefrom".$srctype;
        $srcimg=$funcName($dstFile);
        imagecopyresampled($srcimg,$wtimg,$dstx,$dsty,0,0,$wtSize[0],$wtSize[1],$wtSize[0],$wtSize[1]);
        imagejpeg($srcimg,$dstFile,$quality);
    }
    function hasThumbnail()
    {
        return isset($this->definition["THUMBNAIL"]);
    }
    function getThumbnailWidth()
    {
        return $this->definition["THUMBNAIL"]["WIDTH"];
    }
    function getThumbnailHeight()
    {
        return $this->definition["THUMBNAIL"]["HEIGHT"];
    }
    function hasDescription()
    {
        return isset($this->definition["DESCRIPTION"]);
    }
    function getDescription()
    {
        return $this->definition["DESCRIPTION"];
    }
    function getThumbnailPath()
    {
        if($this->hasValue())
        {
            $prefix=isset($this->definition["THUMBNAIL"]["PREFIX"])?$this->definition["THUMBNAIL"]["PREFIX"]:"th_";
            $info=pathinfo($this->value);
            return $info["dirname"]."/".$prefix.$info['filename'].".".$info["extension"];
        }
        return '';
    }
}

class ImageTypeHTMLSerializer extends FileTypeHTMLSerializer {}

?>
