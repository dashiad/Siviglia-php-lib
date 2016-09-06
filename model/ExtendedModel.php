<?php
namespace lib\model;
/*
        Sus indexFields deben ser relaciones a las claves del objeto padre
*/
class ExtendedModel extends MultipleModel
{
        protected $parentModelName;
        //protected $parentModel;
        protected $mainIndex;
        protected $relatedModel;

        function __construct($serializer=null,$definition=null)
        {
            
                BaseModel::__construct($serializer,$definition);                
                $this->parentModelName=$this->__objectDef["EXTENDS"];
                $this->__setRelatedModelName($this->__objectDef["EXTENDS"]);                
        }

    function loadFromFields()
    {
        if($this->relatedModel)
        {
            $this->relatedModel->loadFromFields();
            $keys=$this->__key->getKeyNames();
            foreach($keys as $curKey)
            {
                $f=$this->__objectDef["FIELDS"][$curKey];
                $remF=array_values($f["FIELDS"]);
                $this->{$curKey}=$this->relatedModel->{$remF[0]};
            }
        }
        BaseModel::loadFromFields();
        return;
        $filters = array();
        $serializer=$this->__getSerializer();
        $keys=$this->__key->getKeyNames();

        // Aqui solo interesan los campos a los que ya se haya accedido.
        foreach ($this->__fields as $key => $value)
        {
            if ($value->isDirty())
            {
                if(in_array($key,$keys))
                {
                    $this->relatedModel=$this->{$keys[0]}[0];
                }
                $filters[] = array("FILTER" => array("F" => $key, "OP" => "=", "V" => \lib\model\types\TypeFactory::serializeType($value->getType(), $serializer->getSerializerType())));
            }
        }
        if (count($filters) == 0)
        { // No existen filters
            $this->__getRelatedModel()->loadFromFields();
            foreach($keys as $val)
                $filters[] = array("FILTER" => array("F" => $key, "OP" => "=", "V" => $this->relatedModel->{$val}));
        }
        try
        {
            $this->__serializer->unserialize($this, array("CONDITIONS" => $filters));
        }
        catch(\Exception $e)
        {
            throw new BaseModelException(BaseModelException::ERR_UNKNOWN_OBJECT);
        }
        $this->__new=false;
        $this->__isDirty=false;
        $this->__loaded=true;
    }
        function __getRelatedModel()
        {
            if(!$this->relatedModel)
            {
                $keys=$this->__key->getKeyNames();
                $this->relatedModel=$this->{$keys[0]}[0];
                if(is_a($this->relatedModel,'\lib\model\BaseTypedModel'))
                {
                    $this->relatedModel->setModelType($this->__objName->className);
                }
                $this->relatedModel->__allowRelay(false);
            }
            return $this->relatedModel;            
        }
             
        function __saveMembers($serializer) {

            
            if($this->__isNew())
            {
                if(!$this->__key->is_set())
                {
                    $instance=$this->__getRelatedModel();
                    $instance->setDirty(true);
                    $instance->save();
                    $this->__key->set($instance);
                }
            }
            else
            {

                // Solo se guarda el related, en caso de que se haya accedido.
                if($this->relatedModel)
                    $this->relatedModel->save();
            }
            
            BaseModel::__saveMembers($serializer);        
    }
    function __call($name,$arguments)
    {
        $instance=$this->__getRelatedModel();
        return call_user_func_array(array($instance,$name),$arguments);
    }
    function delete($serializer=null)
    {
        $this->__getRelatedModel()->delete();
        parent::delete();
    }
}
