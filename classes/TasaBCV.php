<?php
/**
 * Clase para obtener la tasa cambiaria USD/BS del BCV (Banco Central de Venezuela).
 */
class TasaBCV
{
    private const URL_BCV = 'https://www.bcv.org.ve/';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /** @var string|null Último error (cURL o parsing) */
    private $lastError = null;

    /**
     * Obtiene la tasa USD/BS desde la web del BCV.
     * @return float|null Tasa como número o null si falla.
     */
    public function obtenerTasa(): ?float
    {
        $this->lastError = null;
        $html = $this->descargarHtml();

        if (!$html) {
            return null;
        }

        $tasa = $this->extraerTasaDelHtml($html);
        return $tasa;
    }

    /**
     * Descarga el HTML del BCV (cURL preferido, fallback file_get_contents).
     */
    private function descargarHtml(): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init(self::URL_BCV);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            $html = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($err) {
                $this->lastError = $err;
                return null;
            }
            return $html ?: null;
        }

        $options = [
            'http' => [
                'header'  => 'User-Agent: ' . self::USER_AGENT . "\r\n",
                'timeout' => 15,
            ],
            'ssl' => ['verify_peer' => false],
        ];
        $context = stream_context_create($options);
        $html = @file_get_contents(self::URL_BCV, false, $context);
        return $html ?: null;
    }

    /**
     * Extrae el valor numérico de la tasa desde el HTML del BCV.
     */
    private function extraerTasaDelHtml(string $html): ?float
    {
        $patrones = [
            '/<div[^>]*id=["\']dolar["\'][^>]*>.*?<strong[^>]*>\s*([\d,\.]+)\s*<\/strong>/s',
            '/USD\s*<\/[^>]+>\s*<\/[^>]+>\s*[\s\S]*?([\d,\.]+)/',
            '/<div[^>]*>\s*USD\s*<\/div>\s*<div[^>]*>\s*([\d,\.]+)\s*<\/div>/s',
            '/USD.{0,200}?([\d]{2,},\d+)/s',
        ];

        foreach ($patrones as $patron) {
            if (preg_match($patron, $html, $matches) && isset($matches[1])) {
                $tasa = str_replace(',', '.', trim($matches[1]));
                $valor = (float) $tasa;
                if ($valor > 0) {
                    return $valor;
                }
            }
        }

        $this->lastError = 'No se encontró la tasa en el HTML del BCV';
        return null;
    }

    /**
     * Devuelve el último mensaje de error (cURL o parsing).
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
