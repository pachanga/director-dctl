Adobe Director is missing any built in IDE automation tools and this xtra tries to fill this gap.

**dctl** (Director ConTroL) allows to access and control the Adobe Director via the network socket.  The idea is very simple: dctl acts as a server listening on some port using Multiuser networking facility and allows to run a set of predefined tasks(which can be very easily extended, since these tasks are written in Lingo) or eval an arbitrary Lingo string.

The client which can access dctl is written in PHP and can be used from the shell, e.g:

```
c:\dctl HELP
c:\dctl --host=192.168.4.10 STOP
c:\dctl PLAY
c:\dctl EVAL "put #hello"
c:\dctl RELOAD_SCRIPTS #dir /some/dir/with/scripts
```

..etc..

In Lingo tasks look as follows(see tasks.ls in the source for the full listing):

```
on task_STOP me, args
 dispatchCommand(8706)
end
------------------------------------------
on task_PLAY me, args
 dispatchCommand(8705)
end
------------------------------------------
on task_EVAL me, args
 if args.count < 1 then return err("not enough arguments")
 repeat with i=1 to args.count
  str = args[i]
  do str
 end repeat
end
```

Run HELP task in order to see all the tasks available.

Currently dctl works for sure on Windows but it should be no big deal to make it work on Mac as well(it may actually work out of the box, I just don't have a Mac with the Director installed to test it :) )