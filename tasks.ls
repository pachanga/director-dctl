global DCTL_MU
global DCTL_PEERS
global DCTL_STAGE_MOVIE
global DCTL_SCRIPTS_MTIMES

------------------------------------------
on task_PING me, args
  out("ping args:" & args)
end
on help_PING me
  return "sends a pong to the client"  
end
------------------------------------------
on task_HELP me, args
  hls = member("tasks").script.handlers()
  hls.sort()
  out("Available tasks:")
  out("")
  repeat with i=1 to hls.count
    hl = string(hls[i])
    if offset("task_", hl) = 1 then
      task = chars(hl, length("task_") + 1, length(hl))
      help = "help_" & task
      helpstr = ""
      if hls.findPos(help) <> 0 then
        helpstr = " " & call(help, member("tasks").script)
      end if
      out(" " & task & helpstr)
      out("---------")
    end if
  end repeat
end
on help_HELP me
  return "shows this screen"
end
------------------------------------------
on task_RELOAD_SCRIPTS me, args
  if args.count < 2 then return err("not enough arguments")
  
  fx4 = new xtra("FileXtra4")
  
  files = [] 
  -- list of files is passed via args
  if args[1] = "#args" then
    repeat with i=2 to args.count
      files.add(args[i])
    end repeat   
  -- reading file list from a file
  else if args[1] = "#fromfile" then    
    io = new xtra("FileIO")
    error = io.openFile(args[2], 1)     
    file_str = io.readFile()    
    io.closeFile()        
    set the itemdelimiter = RETURN
    repeat with i=1 to file_str.item.count
      files.add(file_str.item[i])
    end repeat   
  -- all scripts in a directory  
  else if args[1] = "#dir" then
    files = fx4.fx_FolderToList(args[2])
  -- parsing delimetered string 
  else if args[1] = "#string" then
    set the itemdelimiter = ";"
    repeat with i=1 to args[2].item.count
      files.add(args[2].item[i])    
    end repeat    
  else    
    return err("unknown argument type '" & args[1] & "'")
  end if
  
  prefix = ""
  if args.count > 2 then prefix = args[3]    
  
  if voidP(DCTL_SCRIPTS_MTIMES) then DCTL_SCRIPTS_MTIMES = [:]  
  
  reloaded = 0
  -- traversing array of files
  repeat with i=1 to files.count    
    file = files[i]     
    -- stripping possible junk from the front of file
    pos = offset(":", file)
    if pos <> 0 then file = chars(file, pos-1, length(file))
    -- getting the member name
    set the itemdelimiter = "\"
    name = the last item of files[i]    
    -- trimming extension
    pos = offset(".", name)
    if pos <> 0 then name = chars(name, 1, pos-1)
    name = prefix & name    
    -- only reloading those members which linked files have changed
    new_mod_time = fx4.fx_FileGetModDate(file)        
    cur_mod_time = DCTL_SCRIPTS_MTIMES.getAProp(file)    
    -- importing or reloading cast members   
    if voidP(DCTL_STAGE_MOVIE.member(name)) then
      mem = DCTL_STAGE_MOVIE.newMember(#script)
      mem.filename = file   
      mem.name = name
      DCTL_SCRIPTS_MTIMES.setAProp(file, new_mod_time)
      reloaded = reloaded + 1
      --out("N " & name)
    else
      if DCTL_STAGE_MOVIE.member(name).type <> #script then
        out("there is conflicting member with name '" & name & "' which is not a script, adding prefix __ to script")
        name = "__" & name
        mem = DCTL_STAGE_MOVIE.newMember(#script)
        mem.filename = file   
        mem.name = name
        DCTL_SCRIPTS_MTIMES.setAProp(file, new_mod_time)
        reloaded = reloaded + 1
        --out("C " & name)        
      else          
        if voidP(cur_mod_time) or (new_mod_time <> cur_mod_time) then                    
          mem = DCTL_STAGE_MOVIE.member(name)
          mem.filename = ""
          mem.filename = file
          DCTL_SCRIPTS_MTIMES.setAProp(file, new_mod_time)
          reloaded = reloaded + 1
          --out("R " & name) 
        end if        
      end if            
    end if
  end repeat
  out("reloaded " & reloaded & " of " & files.count & " scripts")
end
on help_RELOAD_SCRIPTS me
  return "(type={#args|#string|#fromfile|#dir},arg[, prefix]), reloads or imports scripts if neccessary"
end
------------------------------------------
on task_RM_PREFIXED_MEMBERS me, args
  if args.count < 1 then return err("not enough arguments")
  prefix = args[1]  
  traverse_castlib_members(#erase_castlib_member, me, prefix) 
end
on help_RM_PREFIXED_MEMBERS me
  return "(prefix), removes all members with prefix"
end
------------------------------------------
on task_SHOW_CASTLIBS me
  traverse_castlib_members(#print_castlib_member, me)
end
on help_SHOW_CASTLIBS me
  return "shows all members of all castlibs"
end
------------------------------------------
on task_SHOW_STAGE me
  if _player.window["stage"].visible = FALSE then _player.window["stage"].visible = TRUE
  _player.window["stage"].moveToFront()  
end
on help_SHOW_STAGE me
  return "shows stage"
end
------------------------------------------
on task_DISPATCH_CMD me, args
  if args.count < 1 then return err("not enough arguments")  
  -- making cmd a number
  cmd = integer(args[1])
  dispatchCommand(cmd)  
end
on help_DISPATCH_CMD me
  return "(id), emulates pressing the Director menu item with specified id"
end
------------------------------------------
on task_PUBLISH me, args
  dispatchCommand(4104)
end
on help_PUBLISH me
  return "publishes current project, NOTE: requires publish settings to be set"
end
------------------------------------------
on task_STOP me, args
  dispatchCommand(8706)
end
on help_STOP me
  return "stops playback"
end
------------------------------------------
on task_PLAY me, args
  dispatchCommand(8705)
end
on help_PLAY me
  return "starts playback"
end
------------------------------------------
on task_SAVE me, args
  dispatchCommand(4101)
end
on help_SAVE me
  return "saves movie"
end
------------------------------------------
on task_EVAL me, args
  if args.count < 1 then return err("not enough arguments")
  repeat with i=1 to args.count
    str = args[i]
    do str
  end repeat
end
on help_EVAL me
  return "(expr1,[expr2, .., exprN]), evaluates string expression expr, e.g 'out(1+2)', NOTE: use with caution"
end
------------------------------------------
on task_EVAL_HANDLER me, args
  if args.count < 1 then return err("not enough arguments")
  str = args[1]
  -- creating tmp member
  mem = DCTL_STAGE_MOVIE.newMember(#script)
  mem.name = "tmp_" & _system.milliseconds
  -- adding contents to it
  mem.scriptText = str
  hls = mem.script.handlers()
  if hls.count = 0 then return err("no handlers to eval")  
  -- setting args(if any) for this handler
  hl_args = []
  if args.count > 1 then
    repeat with i=2 to args.count
      hl_args.add(args[i])
    end repeat
  end if  
  -- calling handler
  call(hls[1], mem.script, hl_args)
  -- getting rid of it
  mem.erase()
end
on help_EVAL_HANDLER me
  return "(expr[, arg1, arg2, ...]), evaluates string expression expr as a handler, and passes args to it', NOTE: use with caution"
end
------------------------------------------
on traverse_castlib_members delegate, script, args
  repeat with csidx = 1 to DCTL_STAGE_MOVIE.castLib.count
    cl = DCTL_STAGE_MOVIE.castLib[csidx]
    repeat with memidx = 1 to cl.member.count
      mem = cl.member[memidx]
      call(delegate, script, cl, mem, args)
    end repeat
  end repeat
end

on print_castlib_member me, cl, mem
  out("castlib:" & cl.name & " member:" & mem.name & " type:" & mem.type)
end

on reload_castlib_member me, cl, mem, args
  if mem.type <> #script then return  
  if args.count = 0 or (args.count > 0 and args.findPos(mem.name) <> 0) then    
    prevfile = mem.filename
    mem.filename = "dummy"
    mem.filename = prevfile      
  end if 
end

on erase_castlib_member me, cl, mem, prefix
  if offset(prefix, mem.name) = 1 then        
    out("erasing member '" & mem.name & "'")
    mem.erase()
  end if
end

on trim_extension file
  pos = offset(".", file)
  if pos <> 0 then return chars(file, 1, pos-1)
  return file
end
  
