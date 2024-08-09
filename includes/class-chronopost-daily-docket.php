<?php
/**
 * Format Datas to make PDF with FPDF Library
 * @since      1.0.0
 * @package    Chronopost
 * @subpackage Chronopost/includes
 * @author     Adexos <contact@adexos.fr>
 */

class DailyDocketPDF extends FPDF
{
    public function headTable($data, $w = array(75, 75), $mustBold = true)
    {
        foreach ($data as $row) {
            $this->SetFont('Arial', '', 10);
            $this->Cell($w[0], 4, $row[0]);
            if ($mustBold) {
                $this->SetFont('Arial', 'B', 10);
            }
            $this->Cell($w[1], 4, $row[1]);
            $this->Ln();
        }
    }

    public function innerTable($header, $data, $w = array(28, 20, 20, 25, 23, 15, 25, 35))
    {
        $this->SetFillColor(204, 204, 204);
        $this->SetFont('Arial', 'B', 8);

        $sizeof = count($header);
        for ($i = 0; $i < $sizeof; $i++) {
            $this->Cell($w[$i], 6, $header[$i], 1, 0, 'L', true);
        }
        $this->Ln();

        $this->SetFont('Arial', '', 8);
        foreach ($data as $row) {
            $i = 0;
            foreach ($row as $value) {
                $this->Cell($w[$i], 5, $value, 'LRB', 0, 'C');
                $i++;
            }
            $this->Ln();
        }
    }

    public function oxiCell($text, $w = 0, $h = 0)
    {
        $w += $this->GetStringWidth($text);
        $this->Cell($w, $h, $text);
        $this->Ln();
    }
}
