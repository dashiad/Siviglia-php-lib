<?php
namespace lib\output\html\renderers;

class PdfRenderer extends \lib\output\html\renderers\HtmlRenderer
{
    public function render($page, $requestedPath, $outputParams)
    {
        ob_start();
        parent::render($page, $requestedPath, $outputParams);
        $pdf=ob_get_clean();

        require_once LIBPATH.'output/pdf/dompdf/dompdf_config.inc.php';
        $dompdf = new \dompdf();
        $dompdf->set_paper("A4", "portrait");
        $dompdf->load_html(utf8_decode($pdf));
        $dompdf->render();
        $filename = $outputParams['filename'] . '.pdf';
        $dompdf->stream($filename);
    }
}