<?php
class Lock
{
    protected $key   = null;  //user given value
    protected $file  = null;  //resource to lock
    protected $own   = FALSE; //have we locked resource

    function __construct( $path,$key )
    {
        $this->key = $key;
        //create a new resource or get exisitng with same key
        $this->file = fopen("$path/$key.lockfile", 'w+');
        register_shutdown_function(array($this, "clearLocks"));
    }


    function clearLocks()
    {
        if( $this->own == TRUE )
        {
            $this->unlock( );
            $this->own=FALSE;
        }
    }
    function __destruct()
    {
        $this->clearLocks();
    }


    function lock( )
    {
        if( !flock($this->file, LOCK_EX))
        { //failed
            $key = $this->key;
            error_log("ExclusiveLock::acquire_lock FAILED to acquire lock [$key]");
            return FALSE;
        }
        ftruncate($this->file, 0); // truncate file
        //write something to just help debugging
        fwrite( $this->file, "Locked\n");
        fflush( $this->file );

        $this->own = TRUE;
        return $this->own;
    }


    function unlock( )
    {
        $key = $this->key;
        if( $this->own == TRUE )
        {
            if( !flock($this->file, LOCK_UN) )
            { //failed
                error_log("ExclusiveLock::lock FAILED to release lock [$key]");
                return FALSE;
            }
            ftruncate($this->file, 0); // truncate file
            //write something to just help debugging
            fwrite( $this->file, "Unlocked\n");
            fflush( $this->file );
        }
        else
        {
            error_log("ExclusiveLock::unlock called on [$key] but its not acquired by caller");
        }
        $this->own = FALSE;
        return $this->own;
    }
};