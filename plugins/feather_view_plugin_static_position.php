<?php
class Feather_View_Plugin_Static_Position extends Feather_View_Plugin_Abstract{
    public function exec($path, $content = '', Feather_View $view){
        if(!preg_match('#</head>#', $content)){
            $content = '
            <?php  
            $this->set("FEATHER_HEAD_RESOURCE_LOADED", true);
            $this->load("/component/resource/usestyle", $this->get("FEATHER_USE_STYLES")); 
            $this->load("/component/resource/usescript", $this->get("FEATHER_USE_HEAD_SCRIPTS"));
            ?>' . $content;
        }

        if(!preg_match('#</body>#', $content)){
            $content .= '
            <?php
            $this->set("FEATHER_BOTTOM_RESOURCE_LOADED", true);
            $this->load("/component/resource/usescript", $this->get("FEATHER_USE_SCRIPTS"));
            ?>';
        }
        
        return $content;
    }
}