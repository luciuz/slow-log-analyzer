<?php

/**
 * Class ParserException
 */
class ParserException extends \Exception
{
}

/**
 * Class Obj
 */
class Obj
{
    /**
     * @var array
     */
    public $info = [];

    /**
     * @var boolean
     */
    public $infoEnd;

    /**
     * @var string
     */
    public $query;

    /**
     * @var string
     */
    public $simpleQuery;

    /**
     * @var int
     */
    public $similarity;
}

/**
 * Class Parser
 */
class Parser
{
    /**
     * @var int
     */
    public $skip = 3;

    /**
     * @var string
     */
    public $filename;

    /**
     * @var string
     */
    public $resFilename;

    /**
     * @var array
     */
    public $list = [];

    /**
     * @var int
     */
    public $totalCount;

    /**
     * @return bool
     */
    public function validate()
    {
        // filename
        if (is_null($this->filename)) {
            throw new ParserException('Input file is not set.');
        } elseif (!is_file($this->filename)) {
            $this->filename = __DIR__ . '/' . $this->filename;
            if (!is_file($this->filename)) {
                throw new ParserException('Input is not a file.');
            }
        }
        // resFilename
        if (is_null($this->resFilename)) {
            $fn = pathinfo($this->filename, PATHINFO_FILENAME);
            $ext = pathinfo($this->filename, PATHINFO_EXTENSION);
            $dir = pathinfo($this->filename, PATHINFO_DIRNAME);
            $this->resFilename = $dir. '/' . $fn . '.sla' . ($ext ? '.' . $ext : '');
        } elseif (preg_match('~^\w~', $this->resFilename)) {
            $dir = pathinfo($this->filename, PATHINFO_DIRNAME);
            $this->resFilename = $dir . '/' . $this->resFilename;
        } else {
            $dir = pathinfo($this->resFilename, PATHINFO_DIRNAME);
            if (!is_dir($dir)) {
                throw new ParserException('Output dir is invalid.');
            }
        }
        return true;
    }

    public function run()
    {
        if ($this->validate()) {
            $current = new Obj();
            $skipC = 0;
            $fh = fopen($this->filename, "r");
            while ($line = fgets($fh)) {
                // skip
                if ($skipC < $this->skip) {
                    $skipC++;
                    continue;
                }
                // parse
                $this->parse($line, $current);
            }
            $this->add($current);
            fclose($fh);
            return true;
        }
        return false;
    }

    public function write()
    {
        if ($this->list) {
            $fh = fopen($this->resFilename, "w");
            foreach ($this->list as $obj) {
                foreach ($obj->info as $info) {
                    fwrite($fh, $info);
                }
                fwrite($fh, sprintf('# Similarity: %d', $obj->similarity) . PHP_EOL);
                fwrite($fh, $obj->query);
            }
            fclose($fh);
            return true;
        }
        return false;
    }


    /**
     * @param string $line
     * @param Obj $obj
     */
    protected function parse($line, Obj &$obj)
    {
        if (strpos($line, '#') === 0) {
            if ($obj->infoEnd) {
                $this->add($obj);
                $obj = new Obj();
            }
            $obj->info[] = $line;
        } else {
            $obj->infoEnd = true;
            $obj->query .= $line;
        }
    }

    protected function add(Obj $obj)
    {
        if ($this->isNew($obj)) {
            $this->list[] = $obj;
        }
        $this->totalCount++;
    }

    protected function isNew(Obj $obj)
    {
        $this->createSimpleQuery($obj);
        foreach ($this->list as &$item) {
            if ($item->simpleQuery == $obj->simpleQuery) {
                ++$item->similarity;
                return false;
            }
        }
        return true;
    }

    protected function createSimpleQuery(Obj &$obj)
    {
        $query = $obj->query;
        $query = preg_replace('~\d+(?:\.\d+)*~', 'D', $query);
        $query = preg_replace('~\([^)]+\)~', '($)', $query);
        $obj->simpleQuery = $query;
    }
}

/**
 * Code
 */

if (empty($argv[1])) {
    echo 'Usage:', PHP_EOL, '  command input-slow.log [output-slow.log]', PHP_EOL;
    exit;
}

try {
    $parser = new Parser();
    $parser->filename = $argv[1] ?? null;
    $parser->resFilename = $argv[2] ?? null;
    $parser->skip = $argv[3] ?? null;
    $parser->run();
    echo 'RESULTS', PHP_EOL;
    echo 'Total: ', $parser->totalCount, PHP_EOL;
    echo 'Unique: ', count($parser->list), PHP_EOL;
    if ($parser->write()) {
        echo sprintf('File "%s" has been created successfully!', $parser->resFilename), PHP_EOL;
    }
    echo 'Done.', PHP_EOL;
} catch (ParserException $e) {
    echo 'Error: ', $e->getMessage(), PHP_EOL;
}
