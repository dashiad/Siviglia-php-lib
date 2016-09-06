<?php
 namespace lib\model;
 class InverseRelation1x1 extends Relation1x1
 {
        function __construct($name,& $model, $definition, $value=null)
        {

                $targetObject=$definition["OBJECT"];
                if($definition["FIELD"])
                {
                    $src=array("x"=>$definition["FIELD"]);
                }
                else
                {
                    if($definition["FIELDS"])
                    $src=$definition["FIELDS"];                    
                      
                }
                $newFields=array();
                foreach($src as $key1=>$value1)
                {

                    $def=\lib\model\types\TypeFactory::getObjectField($targetObject,$value1);
                    if(!$def)
                    {
                        throw new \lib\model\BaseModelException(\lib\model\BaseModelException::ERR_NOT_A_FIELD,array("field"=>$value1));
                    }
                    if(!$def["FIELDS"])
                    {
                        // Si no es una relacion, es decir, esta relacion inversa esta apuntando a un campo que no esta definido como
                        // una relacion, tomamos la definicion original invertida.
                        // Esto ocurre cuando tenemos un campo en una tabla , que es un identificador, pero que puede apuntar a varias
                        // tablas, dependiendo de algun factor.Es el tipico campo "id_object", que, en cada caso, apunta a un objeto distinto.
                        // Habria que ponerle un tipo especifico a esto, pero, por ahora, simplemente, modificamos la relacion.
                        $def["FIELDS"]=array_flip($definition["FIELDS"]);
                    }
                    foreach($def["FIELDS"] as $keyR=>$valueR)
                    {                        
                        $newFields[$valueR]=$keyR;
                        if(!is_object($model->{"*".$valueR}))
                        {
                            echo $valueR;
                            $h=20;

                        }
                        if($model->{"*".$valueR}->hasOwnValue())
                            $cValue=$model->{$valueR};
                    }
                }                
                $definition["FIELDS"]=$newFields;
                $this->isAlias=true;
                Relation1x1::__construct($name,$model,$definition,$cValue);

        }

     function isInverseRelation()
     {
         return true;
     }
     function requiresUpdateOnNew()
     {
         // Para las relaciones "normales", es decir , A tiene una relacion con B, y estoy guardando A, siempre hay
         // que guardar primero B, obtener su valor, y copiarlo en A.
         // No es posible primero guardar A y luego hacer update de B.
         // Sin embargo, en las relaciones inversas y multiples, si que es necesario primero guardar A, y luego hacer update en B.
         // En la clase de relacion inversa, este metodo se sobreescribe, devolviendo siempre true.
         return true;
     }
     function onModelSaved()
     {
         if(!$this->relation->is_set() && $this->model->__isNew())
         {
             // Tenemos los objetos A y B. B tiene una relacion con A, asi que A tiene una relacion inversa con B, y esta relacion es un alias, y esta clase es ese alias.
             // Aqui estamos en caso de que se ha creado un A, y, a traves de el, uno o varios B.Ahora se ha guardado A, asi que tenemos que copiar el campo relacion, de A, a todos
             // los B que se hayan creado.
             $nObjects=$this->relationValues->count();
             $this->relation->setFromModel($this->model);
             for($k=0;$k<$nObjects;$k++)
             {
                 $cObject=$this->relationValues[$k];
                 $this->relation->setToModel($cObject);
             }
             $this->relationValues->save();

         }
     }

     function getRemoteField()
     {
         $vals=array_values($this->definition["FIELDS"]);
        return $vals[0];
     }
 }
?>
