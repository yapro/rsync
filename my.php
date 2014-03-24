<?php
error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR);

class my {

    private static $instance;  // экземпляра объекта
    private function __construct(){ /* ... @return Singleton */ }  // Защищаем от создания через new Singleton
    private function __clone()    { /* ... @return Singleton */ }  // Защищаем от создания через клонирование
    private function __wakeup()   { /* ... @return Singleton */ }  // Защищаем от создания через unserialize
    public static function rsync() {    // Возвращает единственный экземпляр класса. @return Singleton
        if ( empty(self::$instance) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @var array - список директорий(файлов), которые нужно бэкапить
     */
    private $paths = array();

    /**
     * @var int - номер директории
     */
    private $index = 0;

    /**
     * добавляет в список директорию(файл) которую нужно забэкапить
     * @param string $path - директория(файл) которую нужно забэкапить
     * @return $this
     */
    public function path($path = '')
    {
        $this->index++;

        if( mb_substr($path,-1) !== '/' ){
            $path .= '/';
        }

        $this->paths[ $this->index ] = array( 'path' => $this->clear($path) );

        return $this;
    }

    /**
     * указывает какую субдиректорию(путь) не нужно бэкапить
     * @param string $path - директория(путь) которую не нужно бэкапить
     * @return $this
     */
    public function exclude($path = '')
    {
        $this->paths[ $this->index ]['exclude'][] = $this->clear($path);

        return $this;
    }

    /**
     * @var array - список субдиректорий(путей) которые не нужно бэкапить по всем директориям(путям)
     */
    private $exclude = array();

    /**
     * @param string $path - директория(путь) которую не нужно бэкапить(по всем путям)
     * @return $this
     */
    public function excludeForAll($path = '')
    {
        $this->exclude[] = $this->clear($path);

        return $this;
    }

    /**
     * все субдиректории(пути), которые не нужно бэкапить
     * @param array $exclude
     * @return string
     */
    private function getExclude($exclude = array())
    {
        $array = array();

        if( !empty($exclude) ){
            $array[] = $exclude;
        }

        if( !empty($this->exclude) ){
             $array[] = $this->exclude;
        }

        $paths = '';

        foreach($array as $a){
            foreach($a as $path){
                $paths .= ' --exclude="'.$path.'" ';
            }
        }

        return $paths;
    }

    /**
     * метод проверки директории
     * @param string $path
     * @return string
     * @throws Exception
     */
    private function clear($path = '')
    {
        if( empty($path) || strstr($path, ' ') ){// пробелы запрещены
            throw new Exception($path);
        }

        return $path;
    }

    public function getDate()
    {
        return '`date \+\%Y.\%m.\%d_\%H:\%M:\%S`';
    }

    /**
     * корневая директория, в которой будут храниться все бэкапы
     * @var string
     */
    private $backupDir = '';

    public function setBackupDir($path = '')
    {
        if( empty($path) ){
            throw new Exception($path);
        }

        if( mb_substr($path,-1) === '/' ){
            $path = mb_substr($path,0,-1);
        }

        $this->backupDir = $path;

        return $this;
    }

    /**
     * получаем полный путь директории, в которой будет храниться бэкап
     * @return string
     * @throws Exception
     */
    public function getBackupDir()
    {
        if( empty($this->backupDir) ){
            throw new Exception();
        }

        return $this->backupDir;
    }


    /**
     * корневая директория, в которой будут храниться различия текущей версии бэкапа по сравнению с предыдующей версией
     * @var string
     */
    private $changesDir = '';

    /**
     * @param string $path
     * @return $this
     * @throws Exception
     */
    public function setChangesDir($path = '')
    {
        if( empty($path) ){
            throw new Exception($path);
        }

        if( mb_substr($path,-1) !== '/' ){
            $path .= '/';
        }

        $this->changesDir = $path;

        return $this;
    }

    /**
     * получаем полный путь директории, в которой будет храниться различие текущей версии бэкапа по сравнению с предыдующей версией
     * @return string
     * @throws Exception
     */
    public function getChangesDir()
    {
        if( empty($this->changesDir) ){
            throw new Exception();
        }

        return $this->changesDir;
    }

    /**
     * @var string пользователь и адрес подключения по SSH к Slave-серверу
     */
    private $ssh = 'rsync@yapro.ru';

    /**
     * @param $ssh
     * @return $this
     */
    public function setSsh($ssh)
    {
        $this->ssh = $ssh;
        return $this;
    }

    /**
     * @return string
     */
    public function getSsh()
    {
        return $this->ssh;
    }

    /**
     * @var int - порт по которому можно подключиться по SSH к Slave-серверу
     */
    private $sshPort = 22;

    /**
     * @param $sshPort
     * @return $this
     */
    public function setSshPort($sshPort)
    {
        $this->sshPort = $sshPort;
        return $this;
    }

    /**
     * @return int
     */
    public function getSshPort()
    {
        return $this->sshPort;
    }

    private function toLog($str = '')
    {
        return 'echo '.$str.' > '.dirname(__FILE__).'/log';
    }

    /**
     * метод формирования и сохранения консольных комманд
     */
    public function save()
    {

        $commands[] = '#!/bin/sh

# скрипт создания бэкапов '.dirname(__FILE__).'/run

# проверим, установлен ли rsync и найдем полный путь к программе rsync
RSYNCBIN=`which rsync`
[ ! -x ${RSYNCBIN} ] && {
	echo "rsync not found"
	exit 1
}

# смотрим выполняется ли данное задание
if [ -f '.dirname(__FILE__).'/running ] ; then
  echo " process already running"
  exit
fi

# создаём файл информирующий нас о том, что данное задание запущено
touch '.dirname(__FILE__).'/running

# отключаем сжатие данных в SSH
export RSYNC_RSH="ssh -c arcfour -o Compression=no -x"

CHANGES_DIR="--backup --backup-dir='.$this->getChangesDir().'`date \+\%Y/\%m/\%d/\%H/\%M`"

# запускаем синхронизацию данных';

        // формируем список команд
        $commands[] = $this->toLog('"START"');

        foreach($this->paths as $r){

            if(empty($r['path'])){
                continue;
            }

            $commands[] = $this->toLog('"start '.$this->getDate().' '.$r['path'].'"');

            /*
            ключи:
            -a - архивировать
            -z - сжимать архив
            -c - проверять по хэшам (не обязателен т.к. меняя в файле 1 на 2 - меняется дата изменения
            файла, этого достаточно чтобы копия в
            бэкапе была актуальна). Даже если хакер подменит файл и он будет такой же по весу и дате изменения, он
            просто не обновится в бэкапе.
            --delete-excluded - удалять части которые уже есть на стороне бэкапа, но появились в списке исключения
            --backup - говорим, что делаем бэкап
            */

            // backup-dir=/куда_сохранять_изменения(на сервере А.Б.С.Д) /что_сохранять Rsync@А.Б.С.Д:/куда сохранять_реальный бэкап
            $commands[] = '${RSYNCBIN} --delete-excluded '.$this->getExclude($r['exclude']).' $CHANGES_DIR'.$r['path'].' --delete -e \'ssh -p '.$this->getSshPort().'\' -az '.$r['path'].' '.$this->getSsh().':'.$this->getBackupDir().$r['path'];
		
            $commands[] = $this->toLog('$?');// запишем в лог, то что выдаст нам rsync
            
            $commands[] = $this->toLog('"finish  '.$this->getDate().' '.$r['path'].'"');
	    }

        $commands[] = $this->toLog('"END"');

        $commands[] = '# задание выполнено - удаляем файл информирующий нас о том, что данное задание было запущено
rm -f '.dirname(__FILE__).'/running';

        $runFile = dirname(__FILE__).'/run';

        // записываем команды в файл
        if($fp = fopen($runFile, 'w')){
            fwrite($fp, implode("\n\n", $commands));
            fclose ($fp);
        }
    }
}
