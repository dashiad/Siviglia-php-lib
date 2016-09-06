<?php
namespace lib\reflection\js\jQuery;
class jQueryGenerator
{
    var $model;
    var $parentModel;
    var $currentAction;

    var $stores;
    function __construct($model)
    {
        $this->model=$model;      
        $this->parentModel=$model;
        $this->jsClassName=PROJECTNAME.".model.".$this->normalizeJsName($model);
        $this->stores=array();
    }
    function normalizeJsName($model)
    {
        if($model->objectName->isPrivate())        
            $cad=$model->objectName->getNamespaceModel().".";
        return $cad.$model->objectName->className;
    }
    static function create($layer,$objname,$model)
    {
        return new Model($model);
    }
    function generate()
    {
        $objName=$this->model->objectName;
        $layer=$objName->getLayer();
        $private=$objName->isPrivate();
        $className=$objName->getClassName();
        if($private)
            $className=$objName->getNamespaceModel().".".$className;

        $modelName=$layer.".".$className;
        $text=<<<CLASS
        Siviglia.Utils.buildClass(
       {
            context:'App.$modelName',
            classes:
            {
                Model:
                {
                    inherits:'Siviglia.Model.Instance',
                    construct:function(model)
                    {
                        this.Instance(model);
                    }
                }
            }
       });

CLASS;
        return $text;
    }
    function save()
    {
        $this->saveModel();

        // Se hace lo mismo con las acciones.
        $realActions=$this->model->getActions();
        foreach($realActions as $key=>$value)
        {
            $forms=$value->getForms();
            if(!$forms || count($forms)==0)
                continue;

            $formCode=$this->generateForm($key,$forms[0]);
            if(!$formCode) 
                continue;
            $this->saveForm($forms[0]->name,$formCode);
            
        }
        $relDs=$this->model->getDataSources();
        foreach($relDs as $key=>$value)
        {
            $dsCode=$this->generateDatasourceView($key,$value);
            if(!$dsCode)
                continue;
            $this->saveDatasource($key,$dsCode);
        }
    }
    function saveModel()
    {
        $modelName=$this->model->objectName->getNamespaced();
        $destFile=$this->model->objectName->getDestinationFile("js/Model.js");
        if(!is_file($destFile))
        {
            @mkdir(dirname($destFile),0777,true);
            file_put_contents($destFile,$this->generate());
        }
    }

    function saveForm($formName,$code)
    {
        $destFile=$this->model->objectName->getDestinationFile("js/jQuery/actions/".$formName.".js");
        $targetDir=dirname($destFile);
        @mkdir($targetDir,0777,true);
        $formName=str_replace("Action.",".",$formName);
        file_put_contents($targetDir."/".$formName.".wid",$code["formCode"].$code["formTemplate"]);
    }

    function generateForm($name,$form)
    {        
        
        /*if(!$this->mustRebuild())
            return;*/
        $this->currentAction=$form;
        $fields=$form->getFields();

        $objName=$form->parentModel->objectName;
        $jsNamespace="App.".$objName->getLayer().".".str_replace("\\",".",$objName->getNormalizedName()).".actions";

        if(!$fields)
        {
            return null;
        }
            
        // Se genera primero la template.Esto es asi, porque de esta forma es posible detectar que modulos
        // va a ser necesario cargar en la clase del formulario.
        $formClass=$this->model->objectName->getUnderNamespaced();
        $title=$name;
        $endPoint='/action.php?output=json';
        $def=$form->getDefinition();
        if($def["DESCRIPTION"]) 
            $desc=$def["DESCRIPTION"];
        else
            $desc="";
        $wName=str_replace(".","/",$jsNamespace."/".$name);
        $templateCode=<<<TEMPLATE
<div style="display:none">
        <div sivWidget="$wName" widgetParams="params,node" widgetCode="$jsNamespace.$name">
<div class="SivForm $formClass">
    <div class="SivFormHeader">
        <h2>$title</h2>
        <div class="SivFormDescription">
            $desc
        </div>
    </div>
    <div class="SivFormOkNode"></div>
    <div class="SivFormErrorNode"></div>
    <div class="SivFormBody">
         <form method="post">
         <fieldset class="SivFormContents">
                <div class="SivFieldContainer">
                    <div class="SivFieldContainerTitle"></div>
                    <div class="SivFieldContainerDescription"></div>
TEMPLATE;

      $formAction=$form->getAction();
      $actionRole=$formAction->getRole();
      switch($actionRole)
      {
      case "Add":
          {
              $keys=null;
          }break;
      case "Edit":
          {
              $keys=$form->getIndexFields();
          }break;
      case "AddRelation":
          {
              $keys=$form->getIndexFields();
              $defaultInput="AddRelationMxN";                           
          }break;
      case "SetRelation":
          {
              $keys=$form->getIndexFields();
          }break;
      case "DeleteRelation":
          {
              $keys=$form->getIndexFields();
              $defaultInput="DeleteRelationMxN";
          }break;
          case "SEARCH":
          {
              $keys=null;
          }break;
      default:
          {
              printWarning("No es posible generar codigo para formularios basados en acciones de tipo ".$actionRole);
              return null;
          }
      }
      $errors=array();
        $this->fillActionErrors($form->name,$errors);
        $keyCads=array();
        if($keys)
        {
          foreach($keys as $kkey=>$kvalue)
              $keyCads[]=$kkey;
        }
        $requires=array();
        $fields=$form->getFields();
       foreach($fields as $key=>$valF)
       {
           if($def["INDEXFIELDS"][$key])
                  continue;
              // Aqui, se esta tomando TARGET_RELATION a nivel de campo, no a nivel de form.
            $value=$valF->getDefinition();
              if(isset($value["TARGET_RELATION"]))
              {
                  $targetRel=$value["TARGET_RELATION"];
                  $curField=$form->parentModel->getFieldOrAlias($targetRel);
                  if(isset($def["FIELDS"][$targetRel]))
                      $fDef=$def["FIELDS"][$targetRel];
                  else
                      $fDef=array();
                  //nputsExpr.=$relationField->getFormInput($this->form,$targetRel,$fDef,$def["INPUTS"][$targetRel]);

                  $fieldExpr=$this->getFormInput($targetRel,$curField,$form,$requires);
                  $label=$curField->getLabel();
              }
              else
              {                  
                  $curField=$form->getField($key);
                  $fieldExpr=$this->getFormInput($key,$curField,$form,$requires);
                  $label=$curField->getLabel();
              }
              $templateCode.="\n\t\t\t\t\t\t<div class=\"SivFormContainer ".$key."-input\">\n\t\t\t\t\t\t\t<label class=\"SivFormLabel".($curField->isRequired()?" SivFormRequiredField":"")."\">".
                  $label."</label>\n";
              $templateCode.="\t\t\t\t\t\t\t<span class=\"SivFormInput".($curField->isRequired()?" SivFormRequiredField":"")."\">\n\t\t\t\t".
                  $fieldExpr."\n\t\t\t\t\t\t\t</span>\n\t\t\t\t\t\t</div>\n";
         }

         $templateCode.=<<<TEMPLATE

            </div>
            <div class="SivFormButtons"><button sivEvent="click" sivCallback="submit" type="button">Ok</button></div>
            </fieldset>
    </form>
</div>
             </div>
             </div>
</div>
TEMPLATE;
         // Ahora se prepara el codigo de la clase 

         // Se obtiene la lista de dependencias.


         $actdef=$form->getTranslatedDefinition();

        $modelName=$objName->getFullNormalizedName();
        $jsModelName=$objName->getFullNormalizedName("/");
         // No necesitamos toda la definicion de los campos, solo sus nombres.
         //$actdef["FIELDS"]=array_keys($actdef["FIELDS"]);


         $classCode=<<<CLASSCODE
         [@DEPENDENCY]
            [_BUNDLE]Global[#]
            [_CONTENTS]
            [_CSS]
			    [_CODE]
			        <style type="text/css">
			        </style>
			    [#]
			[#]
            [_SCRIPT]
				[_CODE]
        <script>
         Siviglia.Utils.buildClass({
             context:'$jsNamespace',
             classes:
             {
                '$name':
                {
                    inherits:"Siviglia.UI.Widget,App.Form",

                    methods:
                    {
                        preInitialize:function(params)
                        {

                            this.params=params;
                            this.model=params.model;
                            this.definition=[@OBJECTDEFINITION][_OBJECT]$jsModelName [#][_FORM]$name [#][_OUTPUT]json[#][#];
                            var returnCode=1;
                            if(params.model)
                            {
                                this.model=params.model;
                            }
                            else
                            {
                                var m=this;
                                var tempModel=new Siviglia.Model.Model('$jsModelName');
                                var p=$.Deferred();
                                var callback=function(i){
                                            m.model=i;
                                            m.Form(m.definition, m.model);
                                            p.resolve();
                                    };
                                if(this.definition.ROLE!='EDIT')
                                    tempModel.create().then(callback);
                                else
                                    tempModel.load(params.id).then(callback);
                                return p;
                            }
                        },
                        initialize:function(params)
                        {
                            this.setNode(this.view.rootNode);
                            this.Form\$initialize();
                        }
                    }
                }
             }
         });
         </script>
                [#]
             [#]
         [#]
         [#]
CLASSCODE;
         return array("formCode"=>$classCode,"formTemplate"=>$templateCode);
    }

    function getFormInput($name,$def,$form,& $requires)
    {
        $fDef=$form->getDefinition();
        $extra=$fDef["INPUTS"][$name]["PARAMS"];
        if($extra) {
            if($extra["DATASOURCE"])
            {
                $objName=new \lib\reflection\model\ObjectDefinition($extra["DATASOURCE"]["OBJECT"]);
                $extra["DATASOURCE"]["OBJECT"]=$objName->getNormalizedName();
            }
            $paramData=" data-siviglia-input-params='".json_encode($extra)."'";
        }
        $definition=$def->getDefinition();
        unset($definition["LABEL"]);
        unset($definition["SHORTLABEL"]);
        unset($definition["DESCRIPTION"]);
        unset($definition["DESCRIPTIVE"]);
        unset($definition["ISLABEL"]);
        $ffields=$form->getFields();
        $ffDef=$ffields[$name]->getRawDefinition();
        if($ffDef["MODEL"])
        {
            $resolved=$form->parentModel->resolveField($ffDef["FIELD"]);
            // TODO : Gestionar problemas de campos no encontrados, etc
            $definition["MODEL"]=str_replace("\\","\\\\",$resolved["model"]->objectName->getNormalizedName());
            $definition["FIELD"]=$resolved["field"]->getName();
        }

        $paramData.=" data-siviglia-input-definition='".json_encode($definition)."'";
       // if(!$def->isRelation()) {
            $type=$def->getRawType();
            $keys=array_keys($type);
            $realType=$type[$keys[0]];
            $class=get_class($realType);
            $parts=explode('\\',$class);
            $className=$parts[count($parts)-1];
            $path=$className;
            if(!in_array($path,$requires)) {
                $requires[]=$path;
            }

            return '<span data-siviglia-name="'.$name.'" data-siviglia-input-type="'.$path.'"'."\n\t\t\t\t\t\t".$paramData.'></span>';
            //return "<input name=\"".$name."\" data-dojo-type=\"".$path."\" data-dojo-attach-point=\"".$name."\" $paramData>";
        /*}
        else
        {           
            $inputName=$def->getDefaultInputName();            
            $path="Siviglia/forms/inputs/".$inputName;
             if(!in_array($path,$requires)) {
                $requires[]=$path;
            }
            $remObject=$def->getRemoteObjectName();
            $paramData["OBJECT"]=$remObject;            
            return "<div data-dojo-type=\"".$path."\" data-dojo-attach-point=\"".$name."\" $paramData>";
        }*/
    }
    function fillBaseErrors($typeDef,& $errors)
    {
            if( $typeDef["REQUIRED"] )
                $errors["UNSET"]=1;
            $errors["INVALID"]=2;
    }
    function fillActionErrors($actionName,& $errors)
    {
        $layer=$this->model->objectName->layer;
        $objname=$this->model->objectName->getNormalizedName();
        $destPath=$this->model->objectName->getActionFileName($actionName);
        if(!is_file($destPath))
                return;
        include_once($destPath);        
        $exceptionClass=$this->model->objectName->getNamespacedActionException($actionName);
        
        if( !class_exists($exceptionClass) )
            return;
        
        
        $reflectionClass=new \ReflectionClass($exceptionClass);

        // Se obtienen las constantes
        $constants = $reflectionClass->getConstants ();
        foreach($constants as $key=>$value)
        {        
         if( strpos($key,"ERR_")===0 )
            {
                $key=substr($key,4);
            }
            $errors[$key]=$value;
        }
    }  
    function getRole()
    {
        return $this->currentAction->getRole();
    }
    /*********************************************************
     * @param $name
     * @param $datasource
     * @return array
     *
     *
     *     Generacion de datasources
     *
     *
     *
     *
     *
     */

    function generateDatasourceView($name,$datasource)
    {

        // Los mappings se usan para tener varios filtros que en realidad filtran el mismo campo.
        // Ejemplo: el campo id_user puede ser filtrado por un combo "email" o por un textfield "username".
        // Cualquiera de los dos lo que establece es el campo "id_user".
        $objName=$datasource->parentModel->objectName;
        $jsNamespace="App.".$objName->getLayer().".".str_replace("\\",".",$objName->getNormalizedName()).".views";

        $mappings=array();
        $dsDef=$datasource->getDefinition();
        $groupable=array();
        $addable=array();
        foreach($dsDef["FIELDS"] as $key=>$value)
        {
            if($value["GROUPING"])
                $groupable[]=$key;
            if($value["ALLOW_SUM"])
                $addable[]=$key;
        }

        $needsTabs=false;
        if(count($groupable)>0)
            $needsTabs=true;
        $text='<div sivWidget="'.str_replace(".","/",$jsNamespace)."/".$name.'" widgetParams="params,node" widgetCode="'.$jsNamespace.".".$name.'">';
        $text.="<div class=\"SivDatasource\">\n";
        $text.="\t<div class=\"SivDatasourceHeader\">\n\t\t<h2>".$name."</h2>\n\t\t";
        $text.="\t\t<div class=\"SivDatasourceDescription\">".$dsDef["DESCRIPTION"]."</div>\n";
        $text.="\t\t<div class=\"SivDatasourceFilters\">\n";
        $text.=$this->generateDatasourceForm($datasource,$mappings);
        $text.="\t\t</div>\n";

        if($needsTabs)
        {
            $text.="\t\t<div class=\"tabs\" style=\"width:100%\">\n";
            $text.="\t\t<div class=\"gridPane\" title=\"Listado\">\n";
        }
        $text.=$this->getSaveSelectionDialog();
        $text.=$this->getOpenSelectionDialog();
        $text.=$this->getExecuteActionDialog();
        $text.="\n\t\t<div style=\"text-align:right\">\n";


        $text.="\t\t<input sivEvent=\"click\" sivCallback=\"showSetColumns\" class=\"showSetColumnshButton\" type=\"button\" label=\"Columnas\" iconClass=\"commonIcons dijitIconTable\">\n";
        $text.="\t\t<input sivEvent=\"click\" sivCallback=\"doRefresh\" class=\"refreshButton\" type=\"button\"  label=\"Refresh\" iconClass=\"commonIcons dijitIconUndo\">\n";
        $text.="\t\t<input sivEvent=\"click\" sivCallback=\"showSaveSelectionDialog\" class=\"saveSelectionButton\" type=\"button\"  label=\"Guardar\" iconClass=\"commonIcons dijitIconSave\">\n";
        $text.="\t\t<input sivEvent=\"click\" sivCallback=\"showChooseSelectionDialog\" class=\"saveSelectionButton\" type=\"button\"  label=\"Abrir\" iconClass=\"commonIcons dijitIconFolderOpen\">\n";
        $text.="\t\t<input sivEvent=\"click\" sivCallback=\"showActionsDialog\" class=\"actionsButton\" type=\"button\"  label=\"Ejecutar acción\" iconClass=\"commonIcons dijitIconFunction\">\n";

        /*$text.="\t\t<span>Filas:</span><div data-dojo-type=\"dijit/form/Select\" data-dojo-attach-event=\"onChange:setLines\" data-dojo-attach-point=\"lineSelector\"><option value=\"10\" >10</option><option value=\"20\" selected>20</option><option value=\"50\">50</option><option value=\"100\">100</option></div>&nbsp;&nbsp;<a href=\"\" data-dojo-attach-point=\"downloadCSVLink\">Descargar CSV</a>&nbsp;&nbsp;<a href=\"\" data-dojo-attach-point=\"downloadXLSLink\">Descargar Excel</a></div>\n";
        $text.="<div data-dojo-attach-point=\"showSetColumnsWrapper\" style=\"display:none\"><div data-dojo-attach-point=\"setColumnsContainer\"></div><div><input data-dojo-attach-event=\"onClick:setColumns\" data-dojo-attach-point=\"setColumnsButton\" type=\"button\"  label=\"Aceptar\"></div></div>";*/
        $text.="\t\t<div class=\"SivDatasourceGrid\" style=\"position:relative;height:270px\">\n";
        $text.="\t\t</div>\n";
        $text.="\t</div>";

        if($needsTabs)
        {

            $text.="\t\t</div>\n";
            $text.="\t\t<div class=\"gridPane\" title=\"Charts\" sivEvent=\"click\" sivCallback=\"showCharts\">\n";
            $text.=$this->getDatasourceCharts($datasource,$groupable,$addable);
            $text.="\t\t</div>";
        }
        $text.="\t</div>\n</div>\n";
        $text.="</div>";
        $finalMap=array();
        foreach($mappings as $key=>$value)
        {
            $finalMap[$key]=array_keys($value);
        }
        $inputMappings=json_encode($finalMap);
        $layer=$datasource->parentModel->objectName->layer;

        $fields=$dsDef["FIELDS"];
        $k=0;
        $dojoDef=array();
        $dojoFuncs=array();
        foreach($fields as $key=>$value)
        {
            $keyS=str_replace("/","_",$key);
            $dojoDef[$keyS]=array("name"=>$key,"display"=>true,"order"=>$k);
            $dojoFuncs[$keyS]="\t\t\t\t/*,show_".$keyS.":function(object, value, node, options){ node.innerHTML=value;}*/";
            $k++;
        }
        $fullDojoDef=json_encode($dojoDef);
        $fullDojoFuncs="\n";

        $fullDojoFuncs.=implode("\n",array_values($dojoFuncs));

        $className=str_replace('\\','\\\\',$datasource->parentModel->objectName->getNormalizedName());

        $groupString="";
        $addString="";
        if(count($groupable)>0)
            $groupString="'".implode("','",$groupable)."'";
        if(count($addable)>0)
            $addString="'".implode("','",$addable)."'";

        // Sacar los objetos involucrados en el DS con sus claves primarias.
        $fields = $dsDef['FIELDS'];
        $aModels = array();
        $aux = array();
        foreach($fields as $key=>$value) {
            if (isset($value['MODEL'])) {
                $tModel=\lib\reflection\ReflectorFactory::getModel($value["MODEL"]);
                $field=$tModel->getField($value["FIELD"]);

                if (!in_array($tModel->objectName->getNormalizedName(), $aux)) {
                    $aux[] = $tModel->objectName->getNormalizedName();
                    $i = count($aModels);
                    $aModels[$i]['MODEL'] = $tModel->objectName->getNormalizedName();
                    $indexFields = $tModel->getIndexFields();
                    $aIFields = array();
                    foreach($indexFields as $indexField) {
                        if ($indexField) {
                            $fDef = $indexField->getDefinition();
                            if($indexField->isRelation()) {
                                $pointedField=$indexField->getRemoteFieldNames();
                                $pointedField=$pointedField[0];
                                $remoteObject=$indexField->getRemoteModel();
                                $remoteIndexFields = $remoteObject->getIndexFields();
                                $remoteIndexField = $remoteIndexFields[$pointedField];
                                $rfDef = $remoteIndexField->getDefinition();
                                $aIFields[]=$rfDef['LABEL'];
                            }
                            else {
                                $aIFields[]=$fDef['LABEL'];
                            }
                        }
                    }
                    $aModels[$i]['INDEXFIELDS']=$aIFields;
                }
            }
        }
        $dataObjects = json_encode($aModels);

        $classCode=<<<TEMPLATE
        [@DEPENDENCY]
            [_BUNDLE]Global[#]
            [_CONTENTS]
            [_CSS]
			    [_CODE]
			        <style type="text/css">
			        </style>
			    [#]
			[#]
            [_SCRIPT]
				[_CODE]
         <script type="text/javascript">
         Siviglia.Utils.buildClass({
             context:'$jsNamespace',
             classes:
             {
                '$name':
                {
                    inherits:"Siviglia.Model.List.DataSourceView",
                    methods:
                    {
                        preInitialize:function(params)
                        {
                         this.modelName='$className';
                        this.datasource='$name';
                        this.inputMappings=$inputMappings;
                        this.fieldDefinition=$fullDojoDef;
                        this.groupable=[$groupString];
                        this.addable=[$addString];
                        this.dataObjects=$dataObjects;
                        //this.model=model;
                        //this.definition=definition;

                        },
                        onRow:function(rowNode,row)
                        {
                        },
                        $fullDojoFuncs

                    }
                }
             }
         });
         </script>
                [#]
             [#]
         [#]
         [#]
TEMPLATE;
        return array("dsCode"=>$classCode,"dsTemplate"=>$text);
    }

    function generateDatasourceForm($datasource,& $mappings)
    {
        $dsDef=$datasource->getDefinition();
        $parentObj=$datasource->parentModel;

        $origFields=$dsDef["PARAMS"];
        $createdFilters=array();
        foreach($origFields as $key=>$value)
        {
            if(isset($value["MODEL"]))
            {
                $tModel=\lib\reflection\ReflectorFactory::getModel($value["MODEL"]);
                // Es un campo del modelo actual.
                    // Vemos si es una relacion con otro objeto:
                $field=$tModel->getField($value["FIELD"]);
                //echo $value["FIELD"]."<br>";

                if($field->isRelation())
                {
                    $pointedField=$field->getRemoteFieldNames();
                    $pointedField=$pointedField[0];
                    // Se obtiene el objeto con el que esta relacionado.
                    $remoteObject=$field->getRemoteModel();
                    $remoteNormalized=$remoteObject->objectName->getNormalizedName();
                    $remoteNormalized=str_replace('\\', '\\\\', $remoteNormalized);
                    $remFields=$remoteObject->getSearchableFields();
                    if(count($remFields)==0)
                        continue;
                    $n=0;
                    $cardinality=$remoteObject->getCardinality();
                    if($cardinality>50)
                        $inputType="Relationship";
                    else
                        $inputType="FixedSelect";
                    foreach($remFields as $keyV=>$valueV)
                    {
                        $f=$remoteObject->getField($keyV);
                        $curName=$key."_".$n;
                        $mappings[$key][$curName]=array("LABEL"=>$f->getLabel(),"HTML"=>'<span data-siviglia-name="'.$curName.'" data-siviglia-input-type="'.$inputType.'"  data-siviglia-input-params=\'{"LABEL":["'.$keyV.'"],"VALUE":"'.$pointedField.'","NULL_RELATION":[0],"MAX_RESULTS":20,"PRE_OPTIONS":["Select an option"],"DATASOURCE":{"NAME":"FullList","OBJECT":"'.$remoteNormalized.'"}}\' data-siviglia-input-definition=\'{"DEFAULT":"NULL","FIELDS":{"'.$key.'":"'.$pointedField.'"},"OBJECT":"'.$remoteNormalized.'","TYPE":"'.$inputType.'","MULTIPLICITY":"1:N","ROLE":"HAS_ONE","CARDINALITY":'.$cardinality.'}\'></span>');
                        $labels[$key]=$field->getLabel();
                        $n++;
                    }
                }
                else
                {
                        $createdFilters[$parentObj->objectName->getNamespaced()][$value["FIELD"]]=1;
                        $fields[$key]=$value;
                        continue;
                }
            }
            else
            {
                // Es un campo no relacionado con ningun modelo.
                $fields[$key]=$value;
            }
        }
        if(!$fields && empty($mappings))
        {
            return '';
        }

        $templateCode=<<<TEMPLATE

        <div class="SivForm">\n
            <div class="SivFormBody">\n
                <form method="post" class='mainForm'>
                    <div class="SivFormContents">
                        <div class="SivFieldContainer" style="position:relative">
                        <fieldset>

TEMPLATE;
        if(isset($mappings))
        {
            foreach($mappings as $key=>$value)
            {

                $templateCode.="\n\t\t\t\t\t\t\t\t<div style=\"float:left\">\n";
                $templateCode.="\n\t\t\t\t\t\t\t\t\t<div class=\"title3\">".$labels[$key]."</div>\n";
                foreach($value as $k1=>$v1)
                {
                    $templateCode.="\t\t\t\t\t\t\t\t\t\t\t<label for=\"".$k1."\" class=\"SivFormLabel\">".$v1["LABEL"]."</label>\n";
                    $templateCode.="\t\t\t\t\t\t\t\t\t\t\t".$v1["HTML"]."\n";
                }
                $templateCode.="\t\t\t\t\t\t\t\t</div>\n";
                unset($dsDef["PARAMS"][$key]);
            }
        }
        foreach($dsDef["PARAMS"] as $key=>$value)
        {
            if($this->typeIsFiltrable($value))
            {
            // Aqui, se esta tomando TARGET_RELATION a nivel de campo, no a nivel de form.

            $curField=$this->getDatasourceFormInput($key,$value);
            $templateCode.="\n\t\t\t\t\t\t\t\t<div style=\"float:left\">\n";
            $templateCode.="\n\t\t\t\t\t\t\t\t\t<table border=0>\n";
            $templateCode.="\t\t\t\t\t\t\t\t\t\t<tr>\n";
            $templateCode.="\t\t\t\t\t\t\t\t\t\t\t<td class=\"SivFormLabel\">".$key."</td>\n";
            $templateCode.="\t\t\t\t\t\t\t\t\t\t\t<td class=\"SivFormInput\">\n";
            $templateCode.="\t\t\t\t\t\t\t\t\t\t\t".$curField."\n";
            $templateCode.="\t\t\t\t\t\t\t\t\t\t\t</td>\n";
            $templateCode.="\t\t\t\t\t\t\t\t\t\t</tr>\n";
            $templateCode.="\t\t\t\t\t\t\t\t\t</table>\n";
            $templateCode.="\t\t\t\t\t\t\t\t</div>\n";
            }
        }

        $templateCode.=<<<TEMPLATE
                            </fieldset>
                            <div style="clear:both"></div>
                        </div>
                    </div>

                </form>
            </div>
        </div>
TEMPLATE;

        return $templateCode;
    }
    function typeIsFiltrable($def)
    {
        $type=\lib\model\types\TypeFactory::getType(null,$def);
        $definition=$type->getDefinition();
        if($definition["TYPE"]=="Text")
            return false;
        return true;
    }

    function getDatasourceFormInput($name,$def)
    {
        $type=\lib\model\types\TypeFactory::getType(null,$def);
        $definition=$type->getDefinition();
        $label=$definition["LABEL"]?$definition["LABEL"]:$name;
        $paramData="";
        if($def["MODEL"])
        {
            $model=\lib\reflection\ReflectorFactory::getModel($def["MODEL"]);
            $field=$model->getField($def["FIELD"]);
            $extra=$field->getDefaultInputParams();
        }

        if($extra) {
            $paramData=" data-siviglia-input-params='".json_encode($extra)."'";
        }

        unset($definition["LABEL"]);
        unset($definition["SHORTLABEL"]);
        unset($definition["DESCRIPTION"]);
        unset($definition["DESCRIPTIVE"]);
        unset($definition["ISLABEL"]);

        //Para los filtros de los formularios hacemos que los Boolean se
        //representen como Enum, para poder tener 3 estados (Sí, No, Seleccionar)
        if ($definition['TYPE']=='Boolean') {
            $definition['TYPE'] = 'Enum';
            $definition['VALUES'] = array("No", "Sí");
        }

        $paramData.=" data-siviglia-input-definition='".json_encode($definition)."'";
        // if(!$def->isRelation()) {

        $realType=$type;
        $class=get_class($realType);
        $parts=explode('\\',$class);
        $className=$parts[count($parts)-1];
        $path=$className;

        if ($path==='Boolean') {
            $path = 'Enum';
        }

        return '<span data-siviglia-name="'.$name.'" data-siviglia-input-type="'.$path.'" '.$paramData.'></span>';
    }
    function getDatasourceCharts($datasource,$groupable,$addable)
    {
        $text="\t\t\t<div data-dojo-attach-point=\"chartForm\">\n";
        $dsDef=$datasource->getDefinition();
        for($k=0;$k<count($groupable);$k++)
        {
            $fname=$groupable[$k];
            $d=$dsDef[$fname];
            $text.="\t\t\t\t<div class=\"chart chart".$fname."Container\">\n";
            $text.="\t\t\t\t\t<span class=\"chartLabel\">".($d["LABEL"]?$d["LABEL"]:$fname)."</span>\n";
            $text.="\t\t\t\t\t<input type=\"radio\" data-dojo-type=\"dijit/form/RadioButton\" name=\"groupingField\" value=\"".$fname."\"".($k==0?"checked":"")." data-dojo-attach-event=\"onChange:setGroupingMethod\"/>\n";
            $f=$dsDef["FIELDS"][$fname];
            $defGroupParam=$f["DEFAULTGROUPING"];
            switch($f["GROUPING"])
            {
                case "CONTINUOUS":{
                    $text.="\t\t\t\t\t<div data-dojo-attach-point=\"chart".$fname."Input\" data-dojo-attach-event=\"onChange:setChartVariableValue\" data-dojo-type=\"dijit/form/TextBox\" value=\"".$defGroupParam."\"></div>\n";
                }break;
                case "DISCRETE":{
                    $text.="\t\t\t\t\t<div data-dojo-attach-point=\"chart".$fname."Input\" data-dojo-attach-event=\"onChange:setChartVariableValue\" data-dojo-type=\"dijit/form/TextBox\" value=\"".$defGroupParam."\"></div>\n";
                }break;
                case "DATETIME":{
                    $text.="\t\t\t\t\t<select data-dojo-attach-point=\"chart".$fname."Input\" data-dojo-attach-event=\"onChange:setChartVariableValue\" data-dojo-type=\"dijit/form/Select\">\n";
                    $options=array("MONTHYEAR"=>"Mensual","DATE"=>"Diario","DAY"=>"Por dia","HOUR"=>"Por hora");
                    foreach($options as $jKey=>$jValue)
                        $text.="\t\t\t\t\t\t<option value=\"".$jKey.($jValue==$defGroupParam?" selected":"")."\">".$jValue."</option>\n";

                    $text.="\t\t\t\t\t</select>\n";
                }break;
            }
            $text.="\t\t\t\t</div>\n";
        }
        $text.="\t\t\t<div data-dojo-attach-point=\"GridContainer\"></div>";
        $text.="<div>";
        // Se crean los checkbox para activar/desactivar graficas
        for($k=0;$k<count($addable);$k++)
        {
            $fname=$addable[$k];
            $d=$dsDef[$fname];
            $text.="\t\t\t\t<div data-dojo-attach-point=\"chart".$fname."EnableContainer\">\n";
            $text.="\t\t\t\t\t<span class=\"chartLabelEnable\">Mostrar ".($d["LABEL"]?$d["LABEL"]:$fname)."</span>\n";
            $text.="\t\t\t\t\t<input checked data-dojo-attach-point=\"chart".$fname."Enable\" data-dojo-attach-event=\"change:enableChart\" data-dojo-type=\"dijit/form/CheckBox\">\n";
            $text.="\t\t\t\t</div>\n";
        }
        $text.="\t\t\t</div>\n";
        $text.="\t\t\t<div data-dojo-attach-point=\"chartContainer\" style=\"width:100%;height:400px\"></div>\n";
        return $text;
    }

    function saveDatasource($dsName,$code)
    {
        $destFile=$this->model->objectName->getDestinationFile("js/jQuery/views/".$dsName.".js");
        $targetDir=dirname($destFile);
        @mkdir($targetDir,0777,true);
        file_put_contents($targetDir."/".$dsName.".wid",$code["dsCode"].$code["dsTemplate"]);
    }

    function getSaveSelectionDialog()
    {
        $text = <<<EOL
        <div data-dojo-type="dijit/Dialog" data-dojo-id="saveSelectionDialog" data-dojo-attach-point="saveSelectionDialog" title="Guardar selección">
            <div  data-dojo-type="dijit/layout/ContentPane" data-dojo-id="saveSelectionDialogPane" title="Pane" doLayout="false" style="width: 360px; position: relative; height: 160px;">
                <form data-dojo-type="dijit/form/Form" data-dojo-attach-point="saveSelectionForm">
                    <div>
                        <div style="height: 30px;">
                            <div style="float: left;width: 124px;">Objeto</div>
                            <div style="float: left;margin-left:10px;" data-dojo-attach-point="objectSelectorWrapper"></div>
                        </div>
                        <div style="height: 30px;">
                            <div style="float: left;width: 124px;">
                                <input data-dojo-type="dijit/form/RadioButton" name="groupNameSelector" id="groupNameSelector1" checked value="1" /><label for="groupNameSelector1" style="margin-left:10px;">Nuevo grupo</label>
                            </div>
                            <div style="float: left;margin-left:10px;">
                                <input type="text" data-dojo-attach-point="groupName" data-dojo-type="dijit/form/ValidationTextBox" required="true" />
                            </div>
                        </div>
                        <div style="height: 30px;">
                            <div style="float: left;width: 124px;">
                                <input data-dojo-type="dijit/form/RadioButton" name="groupNameSelector" value="2" id="groupNameSelector2" /><label for="groupNameSelector2" style="margin-left:10px;">Grupo existente</label>
                            </div>
                            <div style="float: left;margin-left:10px;" data-dojo-attach-point="groupSelectorWrapper"></div>
                        </div>
                        <div style="height: 30px;">
                            <div style="float: left;width: 124px;">Nombre de subgrupo</div>
                            <div style="float: left;margin-left:10px;"><input type="text" data-dojo-attach-point="groupMemberName" required="true" data-dojo-type="dijit/form/ValidationTextBox" /></div>
                        </div>
                    </div>
                </form>
                <div style="position: absolute; z-index: 900; bottom: 5px; right: 5px;">
                    <input type="button" data-dojo-type="dijit/form/Button" intermediateChanges="false" label="Cancelar"
                           iconClass="dijitNoIcon" onClick="saveSelectionDialog.onCancel();">
                    <input type="button" data-dojo-type="dijit/form/Button" intermediateChanges="false" label="Aceptar" iconClass="dijitNoIcon"
                           data-dojo-attach-point="saveSelectionButton" data-dojo-attach-event="onClick:saveSelection">
                </div>
            </div>
        </div>
EOL;

        return $text;

    }

    function getOpenSelectionDialog()
    {
        $text = <<<EOL
        <div data-dojo-type="dijit/Dialog" data-dojo-id="chooseSelectionDialog" data-dojo-attach-point="chooseSelectionDialog" title="Abrir selección">
            <div  data-dojo-type="dijit/layout/ContentPane" data-dojo-id="chooseSelectionDialogPane" title="Pane" doLayout="false" style="width: 360px; position: relative; height: 80px;">
                <form data-dojo-type="dijit/form/Form" data-dojo-attach-point="chooseSelectionForm">
                    <div>
                        <div style="height: 30px;display:none;" data-dojo-attach-point="CSdataGroupMemberSelectorContainer">
                            <div style="float: left;width: 124px;">Selección guardada</div>
                            <div style="float: left;margin-left:10px;" data-dojo-attach-point="CSdataGroupMemberSelectorWrapper"></div>
                        </div>
                        <div style="height: 30px;display:none;" data-dojo-attach-point="CSdataGroupMemberSelectorEmpty">
                            No se encuentra ninguna selección guardada coincidente con la selección actual
                        </div>
                    </div>
                </form>
                <div style="position: absolute; z-index: 900; bottom: 5px; right: 5px;">
                    <input type="button" data-dojo-type="dijit/form/Button" intermediateChanges="false" label="Cancelar"
                           iconClass="dijitNoIcon" onClick="chooseSelectionDialog.onCancel();">
                    <input type="button" data-dojo-type="dijit/form/Button" intermediateChanges="false" label="Aceptar" iconClass="dijitNoIcon"
                           data-dojo-attach-point="chooseSelectionButton" data-dojo-attach-event="onClick:chooseSelection">
                </div>
            </div>
        </div>
EOL;

        return $text;
    }

    function getExecuteActionDialog()
    {
        $text = <<<EOL
        <div data-dojo-type="dijit/Dialog" data-dojo-id="executeActionDialog" data-dojo-attach-point="executeActionDialog" title="Ejecutar acción">
            <div  data-dojo-type="dijit/layout/ContentPane" data-dojo-id="executeActionDialogPane" title="Pane" doLayout="false" style="width: 500px; position: relative;">
                <div data-dojo-attach-point="actionsDialogContentWrapper"></div>
            </div>
        </div>
EOL;

        return $text;
    }

}
