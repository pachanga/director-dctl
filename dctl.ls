global DCTL_MU
global DCTL_USER
global DCTL_PORT
global DCTL_PEERS
global DCTL_STAGE_MOVIE
global DCTL_OUT
global DCTL_CURR_MSG
global DCTL_DBG

on log str
  put _system.milliseconds & " : *DCTL* " & str
end

on out str
  log(string(str))
  DCTL_OUT.add(string(str))
end

on err str
  out(str)
  return 1
end

on alertHook me, err, msg, type
  _movie.sendAllSprites(#serverStatus, "alerthook_begin")
  out("ALERT HOOK:" & err & ": " & msg & ", type:" & type)
  if voidP(DCTL_CURR_MSG) = false then
    DCTL_MU.sendNetMessage(DCTL_CURR_MSG.senderID, "ERROR", [err & ": " & msg & ", type:" & type])
  end if  
  _movie.sendAllSprites(#serverStatus, "alerthook_end")
  return 1
end  

on openWindow 
  startServer()
end

on closeWindow
  shutdownServer()
end

on parseCommandLine
  pairs = [:]
  cmd = the commandLine
  set the itemdelimiter = " "
  tmp = []
  repeat with i=1 to cmd.item.count
    tmp.add(cmd.item[i])
  end repeat
  
  set the itemdelimiter = "="
  repeat with i=1 to tmp.count
    if tmp[i].item.count > 1 then
      pairs.setAProp(tmp[i].item[1], tmp[i].item[2])
    end if
  end repeat
  return pairs
end

on startServer
  DCTL_USER = "Admin"
  DCTL_PORT = 1626
  DCTL_DBG = true
  
  opts = parseCommandLine()
  log("command line: " & opts)
  if not voidP(opts.getaProp("dctl_port")) then DCTL_PORT = integer(opts.getaProp("dctl_port"))
  if not voidP(opts.getaProp("dctl_debug")) then DCTL_DBG = true
  
  DCTL_STAGE_MOVIE = _player.window["stage"].movie
  DCTL_PEERS = [:]
  DCTL_MU = new xtra("Multiuser")
  
  DCTL_MU.setNetMessageHandler(#netMessageHandler, 0)
  DCTL_MU.setNetMessageHandler(#peerConnect, 0, "WaitForNetConnection", "System")
  err = DCTL_MU.waitForNetConnection(DCTL_USER, DCTL_PORT)
  
  if err <> 0 then
    log("dctl server startup error: " & getNetError(err))
  else
    log("dctl server is UP on port " & DCTL_PORT)
    _movie.sendAllSprites(#serverStatus, "up")
  end if
end

on shutdownServer
  DCTL_MU = void()
  log("dctl server is DOWN")
  _movie.sendAllSprites(#serverStatus, "down")
end

on peerConnect
  msg = DCTL_MU.getNetMessage()
  if msg.errorCode <> 0 then
    log("Peer connection error: " & getNetError(msg.errorCode))
    return 0
  end if
  log("Peer connection accepted for: " && msg.content)
  DCTL_PEERS.setaProp(msg.content[#userID], "up")
  return 1
end

on netMessageHandler
  
  msg = DCTL_MU.getNetMessage()
  DCTL_CURR_MSG = msg
  
  if voidp(msg) then
    log("Network message is void")
    return
  end if
  
  if msg.errorCode <> 0 and msg.subject = "ConnectionProblem" then
    if DCTL_PEERS.getaProp(msg.content) = "up" then
      log("Peer '" & msg.content & "' disconnected")
      DCTL_PEERS.setaProp(msg.content, "down")
      return
    else
      log("Connection problem for unknown peer: " & msg)
    end if
  end if  
  
  if msg.errorCode <> 0 then
    log("Unknown network message error: " & getNetError(msg.errorCode))
  end if
  
  if msg.subject = "TASK" then
    task = msg.content[1]
    handle = "task_" & task    
    -- parse args
    args = []
    repeat with i=2 to msg.content.count
      args.add(msg.content[i])
    end repeat
    -- find proper handler
    hs = member("tasks").script.handlers()
    pos = hs.findPos(handle)
    if pos = 0 then         
      log("There is no handler for task '" & task & "'")
      DCTL_MU.sendNetMessage(msg.senderID, "ERROR", ["no handler for task '" & task & "'"])
      return
    end if        
    log("Running new task '" & task & "' " & args)
    -- resetting out parameter
    DCTL_OUT = []
    bench = _system.milliseconds    
    -- setting alert hook for operation
    if not DCTL_DBG then      
      prev_hook = _player.alertHook
      _player.alertHook = script("dctl")
    end if  
    _movie.sendAllSprites(#serverStatus, "execute")
    res = call(handle, member("tasks").script, args)
    -- restoring alert hook
    if not DCTL_DBG then _player.alertHook = prev_hook
    -- by default handler is presumed to return "OK"
    if voidP(res) then res = 0    
    log("Task '" & task & "' execution result is '" & res & "'")
    log("(run time " & ((_system.milliseconds-bench)/1000.0) & " sec.)")
    if res <> 0 then
      subj = "ERROR"
      _movie.sendAllSprites(#serverStatus, "done_err")
    else
      subj = "OK"
      _movie.sendAllSprites(#serverStatus, "done_ok")
    end if
    err = DCTL_MU.sendNetMessage(msg.senderID, subj, DCTL_OUT)
  end if  
  
end

on getNetError err
  if (err <> 0) then
    return DCTL_MU.getNetErrorString(err)
  end if
  return ""
end
